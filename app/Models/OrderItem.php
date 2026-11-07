<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $item_type
 * @property int|null $daily_meal_id
 * @property int|null $extra_id
 * @property string $name
 * @property string $unit_price
 * @property int $quantity
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'item_type', 'daily_meal_id', 'extra_id',
        'name', 'unit_price', 'quantity',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
