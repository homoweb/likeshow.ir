<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Models\Product;

/**
 * Single source of truth for pricing. All final prices are calculated on the
 * backend only — values coming from the client are never trusted.
 */
final class PriceCalculator
{
    public const DEFAULT_MIN_QUANTITY = 1000;

    public const DEFAULT_MAX_QUANTITY = 1000000;

    public const DEFAULT_STEP = 1000;

    /**
     * Validate that a quantity is inside the product bounds and matches its
     * step. Throws a CheckoutException with a Persian message otherwise.
     *
     * @throws CheckoutException
     */
    public function validateQuantity(Product $product, int $quantity): void
    {
        $min = max(self::DEFAULT_MIN_QUANTITY, $product->min_quantity);
        $max = min(self::DEFAULT_MAX_QUANTITY, $product->max_quantity);
        $step = $product->step_quantity ?: self::DEFAULT_STEP;

        if ($quantity < $min) {
            throw new CheckoutException("حداقل مقدار مجاز برای این محصول {$min} است.");
        }

        if ($quantity > $max) {
            throw new CheckoutException("حداکثر مقدار مجاز برای این محصول {$max} است.");
        }

        if (($quantity - $min) % $step !== 0) {
            throw new CheckoutException("مقدار انتخابی باید مضربی از {$step} باشد.");
        }
    }

    /**
     * The effective unit price (IRT per 1000 units) for a quantity.
     */
    public function unitPrice(Product $product, int $quantity): int
    {
        return $product->unitPriceFor($quantity);
    }

    /**
     * The total price (IRT) for a quantity of the given product.
     */
    public function total(Product $product, int $quantity): int
    {
        return intdiv($this->unitPrice($product, $quantity) * $quantity, 1000);
    }

    /**
     * The human readable order line for a product, e.g.
     * "۱۰٬۰۰۰ فالوور اینستاگرام".
     */
    public function describe(Product $product, int $quantity): string
    {
        return number_format($quantity).' '.($product->type?->name ?? '').' '.
            ($product->platform?->name ?? '');
    }
}
