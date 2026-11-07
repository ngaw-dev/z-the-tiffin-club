<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatch when an order's status advances
 * (PATCH /api/v1/admin/orders/{id}/status).
 *
 * Payload is the Order (with the new status already persisted).
 * Listeners send the order status update email.
 */
class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
