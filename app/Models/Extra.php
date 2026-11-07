<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $daily_meal_id * @property string $name
 * @property string|null $description
 * @property string $price
 * @property string|null $photo_path
 */
class Extra extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_meal_id', 'name', 'description', 'price', 'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function dailyMeal(): BelongsTo
    {
        return $this->belongsTo(DailyMeal::class);
    }
}
