<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\OrderingWindowService;
use Carbon\CarbonInterface;

/**
 * Read-only ordering-status helper for the storefront.
 *
 * All open/closed decisions live in the Phase 2 OrderingWindowService
 * (App\Services\OrderingWindowService). This class only formats the
 * storefront display payload from the raw settings, and delegates the
 * open/closed booleans to the service when it is available.
 */
class OrderingStatus
{
    public static function payload(?CarbonInterface $now = null): array
    {
        $now ??= now();

        $deliveryOpen = null;
        $pickupOpen = null;

        if (class_exists(OrderingWindowService::class)) {
            $service = app(OrderingWindowService::class);
            $deliveryOpen = $service->deliveryOpen($now);
            $pickupOpen = $service->pickupOpen($now);
        }

        return [
            'delivery_open' => $deliveryOpen,
            'pickup_open' => $pickupOpen,
            'delivery_cutoff' => Setting::get('delivery_cutoff'),
            'pickup_cutoff' => Setting::get('pickup_cutoff'),
            'pickup_window' => [
                'start' => Setting::get('pickup_window_start'),
                'end' => Setting::get('pickup_window_end'),
            ],
            'restaurant_address' => Setting::get('restaurant_address'),
            'delivery_city' => Setting::get('delivery_city'),
        ];
    }
}
