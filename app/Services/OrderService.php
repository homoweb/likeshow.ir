<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates orders with immutable product snapshots and fulfills them
 * idempotently (payment callbacks may arrive multiple times).
 */
final class OrderService
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator,
    ) {}

    /**
     * Create an order from a checkout request. Pricing is recalculated
     * server-side; nothing from the client is trusted.
     *
     * @throws CheckoutException
     */
    public function createFromCheckout(
        ?User $user,
        Product $product,
        int $quantity,
        string $targetUsername,
    ): Order {
        $this->priceCalculator->validateQuantity($product, $quantity);

        $unitPrice = $this->priceCalculator->unitPrice($product, $quantity);
        $totalPrice = $this->priceCalculator->total($product, $quantity);

        return Order::query()->create([
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $user?->getKey(),
            'product_id' => $product->getKey(),
            'product_platform_id' => $product->product_platform_id,
            'product_type_id' => $product->product_type_id,
            'product_title' => $product->title,
            'target_username' => $targetUsername,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }

    /**
     * Mark an order as paid exactly once. Concurrent or repeated callbacks
     * are no-ops thanks to the pessimistic lock and the status guard.
     */
    public function markPaid(Order $order): bool
    {
        return (bool) DB::transaction(function () use ($order): bool {
            /** @var Order $locked */
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->payment_status === PaymentStatus::Paid) {
                return false;
            }

            $locked->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'status' => OrderStatus::Processing,
                'paid_at' => now(),
            ])->save();

            return true;
        });
    }

    /**
     * Unique, human readable order number.
     */
    private function generateOrderNumber(): string
    {
        $prefix = (string) config('likeshow.order.number_prefix', 'LS');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = sprintf(
                '%s-%s-%s',
                $prefix,
                now()->format('ymd'),
                Str::upper(Str::random(5)),
            );

            if (! Order::query()->where('order_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf(
            '%s-%s-%s',
            $prefix,
            now()->format('ymdHis'),
            Str::upper(Str::random(8)),
        );
    }
}
