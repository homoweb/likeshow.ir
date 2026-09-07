<?php

namespace Database\Factories;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductType>
 */
class ProductTypeFactory extends Factory
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
     * A service type other than the seeded defaults (e.g. views).
     */
    public function views(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'views',
            'name' => 'بازدید',
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
