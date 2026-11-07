<?php

namespace App\Services;

use App\Models\DailyMeal;
use App\Models\Extra;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reusable server-side validation for order placement (breakdown §T2.2).
 *
 * Rules enforced (independent of any HTTP layer, so Phase 3 controllers,
 * admin tooling or tests can all reuse it):
 *
 * - Items must reference today's published meal and its extras.
 * - At most `daily_meals.max_per_order` (default 5) meal units per order.
 * - Extras are uncapped.
 * - Delivery requires an address, and its city must equal the
 *   settings.delivery_city launch city. Pickup needs no address.
 * - Orders are rejected after the cutoff for the chosen fulfilment type.
 */
class OrderValidationService
{
    public function __construct(private readonly OrderingWindowService $windows) {}

    /**
     * Validate a prospective order payload and return the normalised line
     * items (models + quantities) with prices taken from the database —
     * never from client input.
     *
     * Expected payload shape:
     * [
     *     'items' => [
     *         ['item_type' => 'meal', 'quantity' => 2],
     *         ['item_type' => 'extra', 'extra_id' => 3, 'quantity' => 1],
     *     ],
     *     'fulfilment_type' => 'delivery',
     *     'delivery_address' => '12 Main St, Wellington',
     * ]
     *
     * @param  array{items?: array<int, array{item_type?: mixed, extra_id?: mixed, quantity?: mixed}>, fulfilment_type?: mixed, delivery_address?: mixed}  $payload
     * @return Collection<int, array{type: string, meal: ?DailyMeal, extra: ?Extra, quantity: int, unit_price: string, name: string}>
     *
     * @throws ValidationException
     */
    public function validate(array $payload, ?CarbonInterface $now = null): Collection
    {
        $now ??= Carbon::now();

        $items = $this->normaliseItems($payload);
        $todayMeal = DailyMeal::query()->forToday()->first();

        $lines = $this->validateItems($items, $todayMeal);
        $this->validateMealCap($lines, $todayMeal);
        $this->validateFulfilment($payload, $now);

        return $lines;
    }

    /**
     * Recompute the order total from database prices (server-side totals).
     */
    public function total(Collection $lines): string
    {
        $total = $lines->sum(
            fn (array $line) => (float) $line['unit_price'] * $line['quantity']
        );

        return number_format($total, 2, '.', '');
    }

    /**
     * @return Collection<int, array{item_type: string, extra_id: ?int, quantity: int}>
     */
    private function normaliseItems(array $payload): Collection
    {
        $raw = Arr::get($payload, 'items');

        if (! is_array($raw) || $raw === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one item is required.',
            ]);
        }

        return collect($raw)
            ->values()
            ->map(function (mixed $item, int $index) {
                if (! is_array($item)) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => 'Invalid item.',
                    ]);
                }

                $type = Arr::get($item, 'item_type');
                $quantity = Arr::get($item, 'quantity', 1);

                if (! in_array($type, ['meal', 'extra'], true)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.item_type" => 'Item type must be meal or extra.',
                    ]);
                }

                if (! is_numeric($quantity) || (int) $quantity < 1 || (int) $quantity > 50) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Quantity must be between 1 and 50.',
                    ]);
                }

                $extraId = $type === 'extra' ? Arr::get($item, 'extra_id') : null;

                if ($type === 'extra' && (! is_numeric($extraId) || (int) $extraId < 1)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.extra_id" => 'A valid extra_id is required for extra items.',
                    ]);
                }

                return [
                    'item_type' => $type,
                    'extra_id' => $extraId !== null ? (int) $extraId : null,
                    'quantity' => (int) $quantity,
                ];
            });
    }

    /**
     * Resolve each line against today's meal and its extras.
     *
     * @param  Collection<int, array{item_type: string, extra_id: ?int, quantity: int}>  $items
     * @return Collection<int, array{type: string, meal: ?DailyMeal, extra: ?Extra, quantity: int, unit_price: string, name: string}>
     */
    private function validateItems(Collection $items, ?DailyMeal $todayMeal): Collection
    {
        if ($todayMeal === null) {
            throw ValidationException::withMessages([
                'items' => 'No meal has been published for today.',
            ]);
        }

        $extras = $todayMeal->extras()->get()->keyBy('id');

        return $items->map(function (array $item, int $index) use ($todayMeal, $extras) {
            if ($item['item_type'] === 'meal') {
                /** @var array{type: string, meal: ?DailyMeal, extra: ?Extra, quantity: int, unit_price: string, name: string} */
                return [
                    'type' => 'meal',
                    'meal' => $todayMeal,
                    'extra' => null,
                    'quantity' => $item['quantity'],
                    'unit_price' => (string) $todayMeal->price,
                    'name' => $todayMeal->name,
                ];
            }

            $extra = $extras->get($item['extra_id']);

            if ($extra === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.extra_id" => 'The selected extra is not available for today\'s meal.',
                ]);
            }

            return [
                'type' => 'extra',
                'meal' => null,
                'extra' => $extra,
                'quantity' => $item['quantity'],
                'unit_price' => (string) $extra->price,
                'name' => $extra->name,
            ];
        });
    }

    /**
     * T2.2: at most `max_per_order` meal units; extras uncapped.
     *
     * @param  Collection<int, array{type: string, meal: ?DailyMeal, extra: ?Extra, quantity: int, unit_price: string, name: string}>  $lines
     */
    private function validateMealCap(Collection $lines, ?DailyMeal $todayMeal): void
    {
        $mealQty = $lines
            ->filter(fn (array $line) => $line['type'] === 'meal')
            ->sum(fn (array $line) => $line['quantity']);

        $max = $todayMeal->max_per_order ?? 5;

        if ($mealQty > $max) {
            throw ValidationException::withMessages([
                'items' => "You can order at most {$max} daily meals per order.",
            ]);
        }
    }

    /**
     * T2.2: delivery address + launch-city check, and per-type cutoff.
     *
     * @param  array{items?: mixed, fulfilment_type?: mixed, delivery_address?: mixed}  $payload
     */
    private function validateFulfilment(array $payload, CarbonInterface $now): void
    {
        $type = Arr::get($payload, 'fulfilment_type');

        if (! in_array($type, ['delivery', 'pickup'], true)) {
            throw ValidationException::withMessages([
                'fulfilment_type' => 'Fulfilment type must be delivery or pickup.',
            ]);
        }

        if ($type === 'delivery') {
            $address = trim((string) Arr::get($payload, 'delivery_address', ''));

            if ($address === '') {
                throw ValidationException::withMessages([
                    'delivery_address' => 'A delivery address is required for delivery orders.',
                ]);
            }

            $city = Setting::get('delivery_city');

            if (stripos($address, (string) $city) === false) {
                throw ValidationException::withMessages([
                    'delivery_address' => "We currently only deliver within {$city}.",
                ]);
            }
        }

        $this->windows->assertCanOrder($type, $now);
    }
}
