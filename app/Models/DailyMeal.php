<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function extras(): HasMany
    {
        return $this->hasMany(Extra::class);
    }

    public function scopeForToday($query)
    {
        return $query->whereDate('menu_date', today());
    }
}
