<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\OrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** Ordered status flow; `cancelled` is reachable from any non-terminal status. */
    private const FLOW = [
        'received' => ['preparing', 'cancelled'],
        'preparing' => ['out_for_delivery', 'ready_for_pickup', 'cancelled'],
        'out_for_delivery' => ['completed', 'cancelled'],
        'ready_for_pickup' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(Order::STATUSES)],
            'fulfilment' => ['nullable', 'string', Rule::in(['delivery', 'pickup'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::query()
            ->with('items')
            ->when($validated['date'] ?? null, fn ($q, $date) => $q->whereDate('ordered_at', $date))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['fulfilment'] ?? null, fn ($q, $fulfilment) => $q->where('fulfilment_type', $fulfilment))
            ->orderByDesc('ordered_at');

        return response()->json(
            $query->paginate($validated['per_page'] ?? 15)->withQueryString()
        );
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $newStatus = $request->validated('status');

        if (! in_array($newStatus, self::FLOW[$order->status] ?? [], true)) {
            return response()->json([
                'message' => "Cannot advance an order with status '{$order->status}' to '{$newStatus}'.",
            ], 422);
        }

        $order->update(['status' => $newStatus]);

        OrderStatusChanged::dispatch($order);

        return response()->json($order->fresh()->load('items'));
    }
}
