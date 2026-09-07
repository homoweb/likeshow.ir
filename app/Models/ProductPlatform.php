<?php

namespace App\Models;

use Database\Factories\ProductPlatformFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable([
    'slug',
    'name',
    'is_active',
    'sort_order',
])]
class ProductPlatform extends Model
{
    /** @use HasFactory<ProductPlatformFactory> */
    use HasFactory;

    /**
     * Products sold under this platform.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
