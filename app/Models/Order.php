<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $user_id
 * @property int $product_id
 * @property int|null $product_platform_id
 * @property int|null $product_type_id
 * @property ProductPlatform|null $platform
 * @property ProductType|null $type
 * @property string $product_title
 * @property string $target_username
 * @property int $quantity
 * @property int $unit_price
 * @property int $total_price
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_number',
    'user_id',
    'product_id',
    'product_platform_id',
    'product_type_id',
    'product_title',
    'target_username',
    'quantity',
    'unit_price',
    'total_price',
    'status',
    'payment_status',
    'paid_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The platform snapshot of the purchased product.
     *
     * @return BelongsTo<ProductPlatform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(ProductPlatform::class, 'product_platform_id');
    }

    /**
     * The service-type snapshot of the purchased product.
     *
     * @return BelongsTo<ProductType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }
}
