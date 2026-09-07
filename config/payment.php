<?php

/*
|--------------------------------------------------------------------------
| Default Payment Configuration (shetabit/payment)
|--------------------------------------------------------------------------
|
| The active driver is selected via PAYMENT_DRIVER. Use `local` for local
| development and automated tests (a fake gateway is rendered locally) and
| a real gateway such as `zarinpal` in production. Credentials are read
| from the environment only — never hard-code them here.
|
*/

use Shetabit\Multipay\Constants\IranCurrency;
use Shetabit\Multipay\Drivers\Local\Local;
use Shetabit\Multipay\Drivers\Zarinpal\Zarinpal;

$mainUrl = rtrim((string) config('likeshow.main_url'), '/');

return [

    'default' => env('PAYMENT_DRIVER', 'zarinpal'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | The local driver renders a fake gateway page that redirects back to the
    | application callback with a success or cancel flag, which makes the
    | complete payment flow testable without a real merchant account.
    |
    */

    'drivers' => [
        'local' => [
            'callbackUrl' => $mainUrl.'/payment/callback',
            'title' => 'درگاه پرداخت آزمایشی',
            'description' => 'این درگاه صرفاً برای تست جریان پرداخت استفاده می‌شود',
            'orderLabel' => 'شماره سفارش',
            'amountLabel' => 'مبلغ قابل پرداخت',
            'payButton' => 'پرداخت موفق',
            'cancelButton' => 'پرداخت ناموفق',
        ],

        'zarinpal' => [
            'merchantId' => env('ZARINPAL_MERCHANT_ID'),
            'callbackUrl' => env('PAYMENT_CALLBACK_URL', $mainUrl.'/payment/callback'),
            'description' => 'پرداخت سفارش لایک شو',
            // sandbox = sandbox.zarinpal.com (test merchant), normal = live gateway
            'mode' => env('ZARINPAL_MODE', 'normal'),
            // Prices in this app are IRT; the driver multiplies by the ratio
            // and sends the rial amount to the gateway. Must be the enum.
            'currency' => IranCurrency::TOMAN,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Class map
    |--------------------------------------------------------------------------
    */

    'map' => [
        'local' => Local::class,
        'zarinpal' => Zarinpal::class,
    ],
];
