<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;

class PickupWindow
{
    /**
     * Human-readable pickup window ("18:00 - 20:00") for pickup orders,
     * or null for delivery orders or missing settings.
     */
    public static function for(Order $order): ?string
    {
        if ($order->fulfilment_type !== 'pickup') {
            return null;
        }

        $start = Setting::get('pickup_window_start');
        $end = Setting::get('pickup_window_end');

        if (! $start || ! $end) {
            return null;
        }

        return $start.' - '.$end;
    }
}
