<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Encapsulates the daily ordering-window rules (brief §7):
 *
 * - A manual override (settings.ordering_open) force-closes all ordering.
 * - Delivery orders close at settings.delivery_cutoff (default 16:00).
 * - Pickup orders close at settings.pickup_cutoff (default 20:00).
 * - Cutoffs are inclusive-exclusive: ordering is open strictly *before* the
 *   cutoff time (15:59 open, 16:00 closed).
 */
class OrderingWindowService
{
    /**
     * Whether delivery orders can currently be placed.
     */
    public function deliveryOpen(?CarbonInterface $now = null): bool
    {
        return $this->openUntil('delivery_cutoff', $now);
    }

    /**
     * Whether pickup orders can currently be placed.
     */
    public function pickupOpen(?CarbonInterface $now = null): bool
    {
        return $this->openUntil('pickup_cutoff', $now);
    }

    /**
     * Overall storefront status: open | delivery_closed | closed.
     *
     * - open: both delivery and pickup can be ordered
     * - delivery_closed: only pickup can still be ordered
     * - closed: nothing can be ordered (manual override, or past pickup cutoff)
     */
    public function status(?CarbonInterface $now = null): string
    {
        $now ??= Carbon::now();

        if (! $this->pickupOpen($now)) {
            return 'closed';
        }

        return $this->deliveryOpen($now) ? 'open' : 'delivery_closed';
    }

    /**
     * Reject an order placement when ordering is closed for the given type.
     *
     * @param  'delivery'|'pickup'  $fulfilmentType
     *
     * @throws ValidationException
     */
    public function assertCanOrder(string $fulfilmentType, ?CarbonInterface $now = null): void
    {
        $now ??= Carbon::now();

        if (Setting::get('ordering_open') !== '1') {
            throw ValidationException::withMessages([
                'fulfilment_type' => 'Ordering is currently closed.',
            ]);
        }

        $open = $fulfilmentType === 'delivery'
            ? $this->deliveryOpen($now)
            : $this->pickupOpen($now);

        if (! $open) {
            $cutoff = $this->cutoffTime(
                $fulfilmentType === 'delivery' ? 'delivery_cutoff' : 'pickup_cutoff',
                $now
            );

            throw ValidationException::withMessages([
                'fulfilment_type' => sprintf(
                    'Ordering for %s has closed for today (cutoff %s).',
                    $fulfilmentType,
                    $cutoff->format('H:i')
                ),
            ]);
        }
    }

    /**
     * True when the manual override is enabled and "now" is before the cutoff.
     */
    private function openUntil(string $cutoffKey, ?CarbonInterface $now = null): bool
    {
        if (Setting::get('ordering_open') !== '1') {
            return false;
        }

        $now ??= Carbon::now();

        return $now->lessThan($this->cutoffTime($cutoffKey, $now));
    }

    /**
     * Today's cutoff as a Carbon instance (same date and timezone as "now").
     */
    private function cutoffTime(string $cutoffKey, CarbonInterface $now): CarbonInterface
    {
        return $now->copy()->setTimeFromTimeString(
            (string) Setting::get($cutoffKey)
        );
    }
}
