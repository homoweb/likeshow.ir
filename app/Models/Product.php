<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $product_platform_id
 * @property int|null $product_type_id
 * @property ProductPlatform|null $platform
 * @property ProductType|null $type
 * @property string $title
 * @property string|null $description
 * @property int $min_quantity
 * @property int $max_quantity
 * @property int $step_quantity
 * @property int $base_price
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable([
    'product_platform_id',
    'product_type_id',
    'title',
    'description',
    'min_quantity',
    'max_quantity',
    'step_quantity',
    'base_price',
    'is_active',
    'sort_order',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The platform taxonomy row this product belongs to.
     *
     * @return BelongsTo<ProductPlatform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(ProductPlatform::class, 'product_platform_id');
    }

    /**
     * The service-type taxonomy row this product belongs to.
     *
     * @return BelongsTo<ProductType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    /**
     * Tiered pricing rules ordered from the cheapest tier.
     *
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)
            ->orderBy('min_quantity')
            ->orderBy('max_quantity');
    }

    /**
     * Orders placed for this product (each keeps an immutable price snapshot).
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The effective unit price (IRT per 1000 units) for a quantity,
     * considering tier rules and falling back to the base price.
     */
    public function unitPriceFor(int $quantity): int
    {
        $tier = $this->prices()
            ->where('min_quantity', '<=', $quantity)
            ->where('max_quantity', '>=', $quantity)
            ->orderBy('min_quantity')
            ->first();

        return $tier?->price ?? $this->base_price;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
