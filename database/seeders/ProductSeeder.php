<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductPlatform;
use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * The default Instagram catalog with tiered pricing.
     */
    public function run(): void
    {
        $instagram = ProductPlatform::query()->where('slug', 'instagram')->firstOrFail();
        $followers = ProductType::query()->where('slug', 'followers')->firstOrFail();
        $likes = ProductType::query()->where('slug', 'likes')->firstOrFail();

        $products = [
            [
                'product_platform_id' => $instagram->getKey(),
                'product_type_id' => $followers->getKey(),
                'title' => 'فالوور اینستاگرام',
                'description' => 'افزایش فالوور واقعی و باکیفیت پیج اینستاگرام شما',
                'min_quantity' => 1000,
                'max_quantity' => 100000,
                'step_quantity' => 1000,
                'base_price' => 90000,
                'sort_order' => 1,
                'tiers' => [
                    ['min_quantity' => 10000, 'max_quantity' => 49999, 'price' => 80000],
                    ['min_quantity' => 50000, 'max_quantity' => 100000, 'price' => 70000],
                ],
            ],
            [
                'product_platform_id' => $instagram->getKey(),
                'product_type_id' => $likes->getKey(),
                'title' => 'لایک اینستاگرام',
                'description' => 'افزایش لایک پست‌های پیج اینستاگرام شما',
                'min_quantity' => 1000,
                'max_quantity' => 50000,
                'step_quantity' => 1000,
                'base_price' => 45000,
                'sort_order' => 2,
                'tiers' => [
                    ['min_quantity' => 20000, 'max_quantity' => 50000, 'price' => 38000],
                ],
            ],
        ];

        foreach ($products as $data) {
            $tiers = $data['tiers'];

            unset($data['tiers']);

            $product = Product::query()->updateOrCreate([
                'product_platform_id' => $data['product_platform_id'],
                'product_type_id' => $data['product_type_id'],
            ], [
                ...$data,
                'is_active' => true,
            ]);

            foreach ($tiers as $tier) {
                $product->prices()->updateOrCreate([
                    'min_quantity' => $tier['min_quantity'],
                ], $tier);
            }
        }
    }
}
