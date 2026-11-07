<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderSummaryResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderLookupController extends Controller
{
    /**
     * GET /api/v1/orders/{orderNumber} — guest lookup by order number.
     */
    public function show(string $orderNumber): JsonResponse
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->with('items')
            ->first();

        abort_if($order === null, 404, 'Order not found.');

        return response()->json(['data' => (new OrderSummaryResource($order))->toArray(request())]);
    }
}
