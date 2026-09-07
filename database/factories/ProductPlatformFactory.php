<?php

namespace Database\Factories;

use App\Models\ProductPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPlatform>
 */
class ProductPlatformFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->regexify('[a-z]{6}'),
            'name' => fake()->word(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A platform other than the seeded defaults (e.g. Telegram).
     */
    public function telegram(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'telegram',
            'name' => 'تلگرام',
        ]);
    }

    /**
     * Hidden from the storefront and new-product forms.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
