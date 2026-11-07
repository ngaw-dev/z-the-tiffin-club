<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $menu_date
 * @property string $name
 * @property string|null $description
 * @property string $price
 * @property string|null $photo_path
 * @property int $max_per_order
 */
class DailyMeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_date', 'name', 'description', 'price', 'photo_path', 'max_per_order',
    ];

    protected function casts(): array
    {
        return [
            'menu_date' => 'date',
            'price' => 'decimal:2',
            'max_per_order' => 'integer',
        ];
    }

    /** @return HasMany<Extra, $this> */
    public function extras(): HasMany
    {
        return $this->hasMany(Extra::class);
    }

    public function scopeForToday($query)
    {
        return $query->whereDate('menu_date', today());
    }
}
