<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $user_id
 * @property string $fulfilment_type
 * @property string $status
 * @property string $customer_name
 * @property string $customer_email
 * @property string $customer_phone
 * @property string|null $delivery_address
 * @property string $payment_method
 * @property string $order_total
 * @property Carbon|null $ordered_at
 */
class Order extends Model
{
    use HasFactory;

    public const STATUSES = [
        'received', 'preparing', 'out_for_delivery', 'ready_for_pickup', 'completed', 'cancelled',
    ];

    protected $fillable = [
        'order_number', 'user_id', 'fulfilment_type', 'status',
        'customer_name', 'customer_email', 'customer_phone', 'delivery_address',
        'payment_method', 'order_total', 'ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'order_total' => 'decimal:2',
            'ordered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
