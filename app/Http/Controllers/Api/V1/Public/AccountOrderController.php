<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderSummaryResource;
use App\Models\Order;
use Illuminate\Http\Request;

class AccountOrderController extends Controller
{
    /**
     * GET /api/v1/account/orders (auth: sanctum) — signed-in order history.
     */
    public function index(Request $request)
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->latest('ordered_at')
            ->paginate(15);

        return OrderSummaryResource::collection($orders);
    }
}
