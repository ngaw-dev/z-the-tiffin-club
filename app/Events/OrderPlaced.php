<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatch when a new order is accepted (POST /api/v1/orders).
 *
 * Payload is the persisted Order with items. Listeners send the
 * order confirmation email.
 */
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
