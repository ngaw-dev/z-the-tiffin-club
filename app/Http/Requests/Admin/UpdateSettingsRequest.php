<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delivery_cutoff' => ['sometimes', 'date_format:H:i'],
            'pickup_cutoff' => ['sometimes', 'date_format:H:i'],
            'pickup_window_start' => ['sometimes', 'date_format:H:i'],
            'pickup_window_end' => ['sometimes', 'date_format:H:i'],
            'ordering_open' => ['sometimes', 'boolean'],
            'restaurant_address' => ['sometimes', 'string', 'max:500'],
            'delivery_city' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function validatedSettings(): array
    {
        $data = $this->validated();
        if (array_key_exists('ordering_open', $data)) {
            $data['ordering_open'] = $data['ordering_open'] ? '1' : '0';
        }

        return $data;
    }
}
