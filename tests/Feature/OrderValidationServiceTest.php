<?php

namespace Tests\Feature;

use App\Models\DailyMeal;
use App\Models\Extra;
use App\Models\Setting;
use App\Services\OrderValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DailyMeal $meal;

    private Extra $extra;

    private OrderValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('ordering_open', '1');
        Setting::set('delivery_cutoff', '16:00');
        Setting::set('pickup_cutoff', '20:00');
        Setting::set('delivery_city', 'Wellington');

        // Comfortably inside both windows unless a test says otherwise.
        // Note: the app runs in UTC; pin everything to one explicit test date
        // so the factory default (`today()`) can never disagree with setTestNow.
        Carbon::setTestNow(Carbon::parse('2026-11-08 12:00'));

        $this->meal = DailyMeal::factory()->forDate(now())->create([
            'name' => 'Butter Chicken Tiffin',
            'price' => 18.50,
            'max_per_order' => 5,
        ]);
        $this->extra = Extra::factory()->create([
            'daily_meal_id' => $this->meal->id,
            'name' => 'Mango Lassi',
            'price' => 5.00,
        ]);

        $this->service = app(OrderValidationService::class);

        // Comfortably inside both windows unless a test says otherwise.
        Carbon::setTestNow(Carbon::parse('2026-11-08 12:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pickupPayload(array $items): array
    {
        return ['items' => $items, 'fulfilment_type' => 'pickup'];
    }

    public function test_valid_meal_and_extra_lines_resolve_with_db_prices(): void
    {
        $lines = $this->service->validate($this->pickupPayload([
            ['item_type' => 'meal', 'quantity' => 2],
            ['item_type' => 'extra', 'extra_id' => $this->extra->id, 'quantity' => 3],
        ]));

        $this->assertCount(2, $lines);

        $mealLine = $lines[0];
        $this->assertSame('meal', $mealLine['type']);
        $this->assertSame(2, $mealLine['quantity']);
        $this->assertSame('18.50', $mealLine['unit_price']);
        $this->assertSame('Butter Chicken Tiffin', $mealLine['name']);

        $extraLine = $lines[1];
        $this->assertSame('Mango Lassi', $extraLine['name']);
        $this->assertSame('5.00', $extraLine['unit_price']);

        // 2 × 18.50 + 3 × 5.00 = 52.00 — server-recomputed total.
        $this->assertSame('52.00', $this->service->total($lines));
    }

    public function test_client_supplied_prices_are_ignored(): void
    {
        // Client tries to sneak a price/unit_price into the payload.
        $lines = $this->service->validate($this->pickupPayload([
            ['item_type' => 'meal', 'quantity' => 1, 'price' => 0.01, 'unit_price' => 0.01],
        ]));

        $this->assertSame('18.50', $lines[0]['unit_price']);
        $this->assertSame('18.50', $this->service->total($lines));
    }

    public function test_max_five_meals_per_order_enforced(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/at most 5 daily meals/');

        $this->service->validate($this->pickupPayload([
            ['item_type' => 'meal', 'quantity' => 6],
        ]));
    }

    public function test_meal_cap_counts_across_multiple_meal_lines(): void
    {
        $this->expectException(ValidationException::class);

        // 3 + 3 = 6 meal units, split across lines to dodge a naive per-line check.
        $this->service->validate($this->pickupPayload([
            ['item_type' => 'meal', 'quantity' => 3],
            ['item_type' => 'meal', 'quantity' => 3],
        ]));
    }

    public function test_exactly_five_meals_is_allowed(): void
    {
        $lines = $this->service->validate($this->pickupPayload([
            ['item_type' => 'meal', 'quantity' => 5],
        ]));

        $this->assertSame(5, $lines[0]['quantity']);
    }

    public function test_owner_can_lower_the_meal_cap(): void
    {
        $this->meal->update(['max_per_order' => 2]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/at most 2 daily meals/');

        $this->service->validate($this->pickupPayload([
            ['item_type' => 'meal', 'quantity' => 3],
        ]));
    }

    public function test_extras_are_uncapped(): void
    {
        $lines = $this->service->validate($this->pickupPayload([
            ['item_type' => 'extra', 'extra_id' => $this->extra->id, 'quantity' => 30],
        ]));

        $this->assertSame(30, $lines[0]['quantity']);
        $this->assertSame('150.00', $this->service->total($lines));
    }

    public function test_delivery_requires_address(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/delivery address is required/');

        $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'delivery',
            'delivery_address' => '',
        ]);
    }

    public function test_delivery_restricted_to_launch_city(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/only deliver within Wellington/');

        $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'delivery',
            'delivery_address' => '10 Queen Street, Auckland',
        ]);
    }

    public function test_delivery_in_launch_city_passes(): void
    {
        $lines = $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'delivery',
            'delivery_address' => '12 Cuba Street, Wellington',
        ]);

        $this->assertCount(1, $lines);
    }

    public function test_delivery_city_check_is_case_insensitive(): void
    {
        $lines = $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'delivery',
            'delivery_address' => '12 Cuba Street, wellington',
        ]);

        $this->assertCount(1, $lines);
    }

    public function test_pickup_does_not_require_address(): void
    {
        $lines = $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'pickup',
        ]);

        $this->assertCount(1, $lines);
    }

    public function test_rejects_delivery_order_after_delivery_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 16:00'));

        try {
            $this->service->validate([
                'items' => [['item_type' => 'meal', 'quantity' => 1]],
                'fulfilment_type' => 'delivery',
                'delivery_address' => '12 Cuba Street, Wellington',
            ]);
            $this->fail('Expected ValidationException for delivery after cutoff.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fulfilment_type', $e->errors());
            $this->assertStringContainsString('cutoff', $e->errors()['fulfilment_type'][0]);
        }
    }

    public function test_allows_pickup_order_after_delivery_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 16:30'));

        $lines = $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'pickup',
        ]);

        $this->assertCount(1, $lines);
    }

    public function test_rejects_pickup_order_after_pickup_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 20:00'));

        $this->expectException(ValidationException::class);

        $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'pickup',
        ]);
    }

    public function test_rejects_order_when_manual_override_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 12:00'));
        Setting::set('ordering_open', '0');

        $this->expectException(ValidationException::class);

        $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'pickup',
        ]);
    }

    public function test_rejects_unknown_extra(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/not available for today/');

        $otherMeal = DailyMeal::factory()->create(['menu_date' => now()->subDay()]);
        $oldExtra = Extra::factory()->create(['daily_meal_id' => $otherMeal->id]);

        $this->service->validate($this->pickupPayload([
            ['item_type' => 'extra', 'extra_id' => $oldExtra->id, 'quantity' => 1],
        ]));
    }

    public function test_rejects_order_when_no_meal_published_today(): void
    {
        $this->meal->delete();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/No meal has been published/');

        $this->service->validate($this->pickupPayload([
            ['item_type' => 'meal', 'quantity' => 1],
        ]));
    }

    public function test_rejects_empty_items(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validate(['items' => [], 'fulfilment_type' => 'pickup']);
    }

    public function test_rejects_invalid_item_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validate($this->pickupPayload([
            ['item_type' => 'dessert', 'quantity' => 1],
        ]));
    }

    public function test_rejects_invalid_quantity(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validate($this->pickupPayload([
            ['item_type' => 'meal', 'quantity' => 0],
        ]));
    }

    public function test_rejects_invalid_fulfilment_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->validate([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
            'fulfilment_type' => 'courier',
        ]);
    }
}
