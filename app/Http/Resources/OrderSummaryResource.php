<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status,
            'fulfilment_type' => $this->fulfilment_type,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'delivery_address' => $this->delivery_address,
            'payment_method' => $this->payment_method,
            'order_total' => $this->order_total,
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'items' => $this->items->map(fn ($item) => [
                'item_type' => $item->item_type,
                'name' => $item->name,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => number_format((float) $item->unit_price * $item->quantity, 2, '.', ''),
            ])->values(),
        ];
    }
}
