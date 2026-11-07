<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\DailyMeal;
use App\Support\OrderingStatus;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    /**
     * GET /api/v1/menu/today — today's published meal + extras + ordering status.
     */
    public function today(): JsonResponse
    {
        $meal = DailyMeal::query()
            ->forToday()
            ->with('extras')
            ->first();

        abort_if($meal === null, 404, 'No meal has been published for today.');

        return response()->json([
            'data' => [
                'meal' => [
                    'date' => $meal->menu_date->toDateString(),
                    'name' => $meal->name,
                    'description' => $meal->description,
                    'price' => $meal->price,
                    'photo_url' => $meal->photo_path ? asset('storage/'.$meal->photo_path) : null,
                    'max_per_order' => $meal->max_per_order,
                    'extras' => $meal->extras->map(fn ($extra) => [
                        'id' => $extra->id,
                        'name' => $extra->name,
                        'description' => $extra->description,
                        'price' => $extra->price,
                        'photo_url' => $extra->photo_path ? asset('storage/'.$extra->photo_path) : null,
                    ])->values(),
                ],
                'ordering' => OrderingStatus::payload(),
            ],
        ]);
    }
}
