<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const DEFAULTS = [
        'delivery_cutoff' => '16:00',
        'pickup_cutoff' => '20:00',
        'pickup_window_start' => '18:00',
        'pickup_window_end' => '20:00',
        'ordering_open' => '1',
        'restaurant_address' => '123 Cuba Street, Wellington',
        'delivery_city' => 'Wellington',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value')
            ?? static::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
