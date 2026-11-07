<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMealRequest;
use App\Http\Requests\Admin\UpdateMealRequest;
use App\Models\DailyMeal;
use App\Models\OrderItem;
use App\Services\PhotoUploadService;
use Illuminate\Http\JsonResponse;

class MealController extends Controller
{
    public function __construct(
        private readonly PhotoUploadService $photos,
    ) {}

    public function store(StoreMealRequest $request): JsonResponse
    {
        $date = $request->date('date') ?? today();

        if (DailyMeal::query()->whereDate('menu_date', $date)->exists()) {
            return response()->json([
                'message' => 'A meal is already published for this date. Use PUT /api/v1/admin/meals/{date} to amend it.',
            ], 409);
        }

        $meal = DailyMeal::query()->create([
            'menu_date' => $date,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'max_per_order' => $request->validated('max_per_order') ?? 5,
            'photo_path' => $request->file('photo')
                ? $this->photos->store($request->file('photo'), 'meals')['path']
                : null,
        ]);

        return response()->json(['data' => $this->present($meal)], 201);
    }

    public function update(UpdateMealRequest $request, string $date): JsonResponse
    {
        $meal = DailyMeal::query()->whereDate('menu_date', $date)->first();

        if (! $meal) {
            return response()->json(['message' => 'Meal not found.'], 404);
        }

        $attributes = collect($request->validated())->except('photo')->all();
        if ($request->file('photo')) {
            $this->photos->delete($meal->photo_path);
            $attributes['photo_path'] = $this->photos->store($request->file('photo'), 'meals')['path'];
        }

        $meal->update($attributes);

        return response()->json(['data' => $this->present($meal->fresh())]);
    }

    public function destroy(string $date): JsonResponse
    {
        $meal = DailyMeal::query()->whereDate('menu_date', $date)->first();

        if (! $meal) {
            return response()->json(['message' => 'Meal not found.'], 404);
        }

        $hasOrders = OrderItem::query()
            ->where('item_type', 'meal')
            ->where('daily_meal_id', $meal->id)
            ->exists();

        if ($hasOrders) {
            return response()->json([
                'message' => 'Cannot unpublish this meal: orders already reference it.',
            ], 409);
        }

        $this->photos->delete($meal->photo_path);
        $meal->extras()->delete();
        $meal->delete();

        return response()->json(['message' => 'Meal unpublished.']);
    }

    /** @return array<string, mixed> */
    private function present(DailyMeal $meal): array
    {
        return [
            'id' => $meal->id,
            'date' => $meal->menu_date->toDateString(),
            'name' => $meal->name,
            'description' => $meal->description,
            'price' => $meal->price,
            'max_per_order' => $meal->max_per_order,
            'photo_url' => $this->photos->url($meal->photo_path),
            'photo_thumb_url' => $this->photos->url($this->thumbPath($meal->photo_path)),
        ];
    }

    private function thumbPath(?string $path): ?string
    {
        return $path ? preg_replace('/-full\.jpg$/', '-thumb.jpg', $path) : null;
    }
}
