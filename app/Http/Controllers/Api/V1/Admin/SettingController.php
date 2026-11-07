<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => collect(Setting::DEFAULTS)
                ->mapWithKeys(fn ($default, $key) => [$key => Setting::get($key)])
                ->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        foreach ($request->validatedSettings() as $key => $value) {
            Setting::set($key, (string) $value);
        }

        return $this->show();
    }
}
