<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtraRequest;
use App\Models\DailyMeal;
use App\Models\Extra;
use App\Models\OrderItem;
use App\Services\PhotoUploadService;
use Illuminate\Http\JsonResponse;

class ExtraController extends Controller
{
    public function __construct(
        private readonly PhotoUploadService $photos,
    ) {}

    public function index(): JsonResponse
    {
        $meal = $this->todayMeal();

        if (! $meal) {
            return response()->json([
                'message' => 'No meal published for today. Publish a meal first.',
            ], 404);
        }

        return response()->json([
            'data' => $meal->extras()->orderBy('id')->get()->map(fn (Extra $extra) => $this->present($extra))->all(),
        ]);
    }

    public function store(ExtraRequest $request): JsonResponse
    {
        $meal = $this->todayMeal();

        if (! $meal) {
            return response()->json([
                'message' => 'No meal published for today. Publish a meal first.',
            ], 404);
        }

        $extra = $meal->extras()->create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'photo_path' => $request->file('photo')
                ? $this->photos->store($request->file('photo'), 'extras')['path']
                : null,
        ]);

        return response()->json(['data' => $this->present($extra)], 201);
    }

    public function show(Extra $extra): JsonResponse
    {
        return response()->json(['data' => $this->present($extra)]);
    }

    public function update(ExtraRequest $request, Extra $extra): JsonResponse
    {
        $attributes = collect($request->validated())->except('photo')->all();
        if ($request->file('photo')) {
            $this->photos->delete($extra->photo_path);
            $attributes['photo_path'] = $this->photos->store($request->file('photo'), 'extras')['path'];
        }

        $extra->update($attributes);

        return response()->json(['data' => $this->present($extra->fresh())]);
    }

    public function destroy(Extra $extra): JsonResponse
    {
        $hasOrders = OrderItem::query()
            ->where('item_type', 'extra')
            ->where('extra_id', $extra->id)
            ->exists();

        if ($hasOrders) {
            return response()->json([
                'message' => 'Cannot delete this extra: orders already reference it.',
            ], 409);
        }

        $this->photos->delete($extra->photo_path);
        $extra->delete();

        return response()->json(['message' => 'Extra deleted.']);
    }

    private function todayMeal(): ?DailyMeal
    {
        return DailyMeal::query()->forToday()->first();
    }

    /** @return array<string, mixed> */
    private function present(Extra $extra): array
    {
        return [
            'id' => $extra->id,
            'daily_meal_id' => $extra->daily_meal_id,
            'name' => $extra->name,
            'description' => $extra->description,
            'price' => $extra->price,
            'photo_url' => $this->photos->url($extra->photo_path),
        ];
    }
}
