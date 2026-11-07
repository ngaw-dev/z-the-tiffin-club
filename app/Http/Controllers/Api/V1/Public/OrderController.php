<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\PlaceOrderRequest;
use App\Http\Resources\OrderSummaryResource;
use App\Models\Order;
use App\Services\OrderValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class OrderController extends Controller
{
    /**
     * POST /api/v1/orders — place an order (guest or signed-in).
     *
     * Business rules (§T2.2) and total computation are delegated to the
     * Phase 2 OrderValidationService; this controller never re-implements
     * them. Prices and item names are snapshotted from the database.
     */
    public function store(PlaceOrderRequest $request, OrderValidationService $validation): JsonResponse
    {
        $key = 'place-order:'.($request->resolvedUser()->id ?? $request->ip());

        $allowed = RateLimiter::attempt(
            $key,
            $perMinute = 5,
            fn () => true
        );

        if (! $allowed) {
            return response()->json([
                'message' => 'Too many orders. Please wait a minute before trying again.',
            ], 429)->withHeaders([
                'Retry-After' => RateLimiter::availableIn($key),
            ]);
        }

        $lines = $validation->validate($request->orderPayload());
        $total = $validation->total($lines);

        $order = Order::create([
            'order_number' => $this->nextOrderNumber(),
            'user_id' => $request->resolvedUser()?->id,
            'fulfilment_type' => $request->string('fulfilment_type'),
            'status' => 'received',
            'customer_name' => $request->string('customer_name')->trim(),
            'customer_email' => $request->string('customer_email')->lower(),
            'customer_phone' => $request->string('customer_phone')->trim(),
            'delivery_address' => $request->fulfilment_type === 'delivery'
                ? $request->string('delivery_address')->trim()
                : null,
            'payment_method' => $request->string('payment_method', 'cash'),
            'order_total' => $total,
            'ordered_at' => now(),
        ]);

        foreach ($lines as $line) {
            $order->items()->create([
                'item_type' => $line['type'],
                'daily_meal_id' => $line['meal']?->id,
                'extra_id' => $line['extra']?->id,
                'name' => $line['name'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
            ]);
        }

        $order->load('items');

        return (new OrderSummaryResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Human-readable sequential order number, e.g. TB-000123.
     */
    private function nextOrderNumber(): string
    {
        $last = Order::query()->max('id') ?? 0;

        do {
            $number = 'TB-'.str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
            $last++;
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
