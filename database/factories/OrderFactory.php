<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomElement([1000, 5000, 10000]);
        $unitPrice = fake()->numberBetween(80, 200);

        return [
            'order_number' => 'FB-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'product_title' => 'فالوور اینستاگرام',
            'target_username' => fake()->userName(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => intdiv($quantity * $unitPrice, 1000),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ];
    }

    /**
     * Mirror the purchased product's taxonomy into the snapshot columns.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            $order->forceFill([
                'product_platform_id' => $order->product->product_platform_id,
                'product_type_id' => $order->product->product_type_id,
            ])->save();
        });
    }

    /**
     * A fully paid, completed order (snapshot mirrors a followers product).
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * A canceled order.
     */
    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Canceled,
        ]);
    }

    /**
     * Guest order without a user account.
     */
    public function guest(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
        ]);
    }
}
