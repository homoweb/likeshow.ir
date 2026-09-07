<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Shetabit\Multipay\Constants\IranCurrency;
use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Drivers\Zibal\Zibal;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Payment as PaymentManager;
use Shetabit\Multipay\Receipt;
use Shetabit\Multipay\RedirectionForm;

beforeEach(function () {
    config(['payment.default' => 'local']);
});

test('the zarinpal driver configuration is gateway-compatible', function () {
    $settings = config('payment.drivers.zarinpal');

    // The driver multiplies the toman amount by `$currency->ratio()`; a
    // string instead of the enum crashes the purchase with a fatal error.
    expect($settings['currency'])->toBeInstanceOf(IranCurrency::class)
        ->and($settings['currency']->ratio())->toBe(10)
        ->and($settings['mode'])->toBe('normal')
        ->and($settings['callbackUrl'])->toEndWith('/payment/callback');
});

test('the zibal driver configuration is gateway-compatible', function () {
    $settings = config('payment.drivers.zibal');

    expect($settings['currency'])->toBeInstanceOf(IranCurrency::class)
        ->and($settings['currency']->ratio())->toBe(10)
        ->and($settings['mode'])->toBeIn(['normal', 'direct'])
        ->and($settings['callbackUrl'])->toEndWith('/payment/callback')
        ->and(config('payment.map.zibal'))->toBe(Zibal::class);
});

/**
 * A PaymentManager whose zibal flow is faked end-to-end: purchase hands out
 * a fixed trackId, pay() points at gateway.zibal.ir and verify() accepts.
 * This exercises the application's own zibal plumbing (authority storage,
 * trackId callbacks, idempotent verification) without any HTTP. The optional
 * behavior closures let the failure-path tests throw gateway exceptions.
 */
function zibalManager(): PaymentManager
{
    return new class extends PaymentManager
    {
        public ?Closure $purchaseBehavior = null;

        public ?Closure $verifyBehavior = null;

        public function __construct()
        {
            $this->invoice(new Invoice);
        }

        public function via(string $driver): static
        {
            return $this;
        }

        public function purchase(?Invoice $invoice = null, ?callable $finalizeCallback = null): static
        {
            if ($this->purchaseBehavior !== null) {
                ($this->purchaseBehavior)($invoice);

                return $this;
            }

            if ($invoice instanceof Invoice) {
                $this->invoice($invoice);
            }

            $this->invoice->transactionId('987654321');

            return $this;
        }

        public function pay(?callable $initializeCallback = null): RedirectionForm
        {
            // The local driver may have swapped the shared static view path.
            RedirectionForm::setViewPath(RedirectionForm::getDefaultViewPath());

            return new RedirectionForm('https://gateway.zibal.ir/start/987654321', [], 'GET');
        }

        public function verify(?callable $finalizeCallback = null): ReceiptInterface
        {
            if ($this->verifyBehavior !== null) {
                return ($this->verifyBehavior)();
            }

            return new Receipt('zibal', '981234567');
        }
    };
}

function bindPaymentManager(PaymentManager $manager): void
{
    // The manager must be bound under the package's own service name; the
    // class-name alias resolves to it.
    app()->instance(Shetabit\Payment\Facade\Payment::SERVICE_NAME, $manager);
}

test('a zibal payment redirects to the gateway and is verified from its trackId callback', function () {
    config(['payment.default' => 'zibal']);
    bindPaymentManager(zibalManager());

    $user = User::factory()->create();
    $product = Product::factory()->followers()->withTiers()->create();

    $order = Order::factory()->for($user)->for($product)->create([
        'quantity' => 5000,
        'unit_price' => 120,
        'total_price' => 600,
    ]);

    $response = $this->actingAs($user)
        ->post('https://likeshow.test/payment/start/'.$order->id);

    $response->assertOk();
    expect($response->getContent())->toContain('https://gateway.zibal.ir/start/987654321');

    $payment = Payment::query()->where('order_id', $order->id)->sole();
    expect($payment->gateway)->toBe('zibal')
        ->and($payment->status->value)->toBe('pending')
        ->and($payment->authority)->toBe('987654321');

    // Zibal returns the customer with its trackId; the app must locate the
    // payment by exactly that token, verify and fulfill the order.
    $callback = $this->get('https://likeshow.test/payment/callback?trackId=987654321');

    $callback->assertRedirect();
    expect($callback->headers->get('Location'))->toContain('/payment/result/');
    $callback->assertSessionHas('success');

    $order->refresh();
    expect($order->payment_status->value)->toBe('paid')
        ->and($order->status->value)->toBe('processing')
        ->and($order->paid_at)->not->toBeNull();

    $payment->refresh();
    expect($payment->status->value)->toBe('success')
        ->and($payment->reference_id)->toBe('981234567');

    // Replaying the trackId callback must never double-fulfill the order.
    $this->get('https://likeshow.test/payment/callback?trackId=987654321')
        ->assertRedirect();

    expect($order->refresh()->payment_status->value)->toBe('paid')
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('a failed gateway purchase returns to the review page with an error and stays retryable', function () {
    config(['payment.default' => 'zibal']);

    $manager = zibalManager();
    $manager->purchaseBehavior = fn (?Invoice $invoice) => throw new PurchaseFailedException('مرچنت نامعتبر است.');
    bindPaymentManager($manager);

    $user = User::factory()->create();
    $product = Product::factory()->followers()->withTiers()->create();

    $order = Order::factory()->for($user)->for($product)->create([
        'quantity' => 5000,
        'unit_price' => 120,
        'total_price' => 600,
    ]);

    $this->actingAs($user)
        ->post('https://likeshow.test/payment/start/'.$order->id)
        ->assertRedirect(route('main.payment.review', $order))
        ->assertSessionHas('error', 'مرچنت نامعتبر است.');

    // A failed purchase must not create a payment row...
    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(0);

    // ...and the order must stay payable once the gateway recovers.
    $manager->purchaseBehavior = null;

    $this->post('https://likeshow.test/payment/start/'.$order->id)->assertOk();
});

test('a zibal IP-whitelist rejection surfaces actionable guidance', function () {
    config(['payment.default' => 'zibal']);

    $manager = zibalManager();
    // Zibal reports an unwhitelisted server IP as result 115, which the
    // shared driver maps to its generic unknown-error message; the code
    // must still translate it into guidance the merchant can act on.
    $manager->purchaseBehavior = fn (?Invoice $invoice) => throw new PurchaseFailedException(
        'خطای ناشناخته ای رخ داده است.',
        115,
    );
    bindPaymentManager($manager);

    $user = User::factory()->create();
    $product = Product::factory()->followers()->withTiers()->create();

    $order = Order::factory()->for($user)->for($product)->create([
        'quantity' => 5000,
        'unit_price' => 120,
        'total_price' => 600,
    ]);

    $response = $this->actingAs($user)
        ->post('https://likeshow.test/payment/start/'.$order->id);

    $response->assertRedirect(route('main.payment.review', $order))
        ->assertSessionHas('error');

    expect($response->getSession()->get('error'))->toContain('IP')
        ->toContain('زیبال');

    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(0);
});

test('an unpaid zibal callback fails the payment instead of erroring', function () {
    config(['payment.default' => 'zibal']);

    $manager = zibalManager();
    // Zibal reports result 202 (unpaid / canceled order) as a gateway
    // failure rather than an invalid-payment exception.
    $manager->verifyBehavior = fn () => throw new PurchaseFailedException('سفارش پرداخت نشده یا ناموفق بوده است.');
    bindPaymentManager($manager);

    $user = User::factory()->create();
    $product = Product::factory()->followers()->withTiers()->create();

    $order = Order::factory()->for($user)->for($product)->create([
        'quantity' => 5000,
        'unit_price' => 120,
        'total_price' => 600,
    ]);

    Payment::factory()->for($order)->create([
        'gateway' => 'zibal',
        'authority' => '987654321',
    ]);

    $response = $this->get('https://likeshow.test/payment/callback?trackId=987654321');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/payment/result/');
    $response->assertSessionHas('error', 'سفارش پرداخت نشده یا ناموفق بوده است.');

    $payment = Payment::query()->where('authority', '987654321')->sole();
    expect($payment->status->value)->toBe('failed')
        ->and($payment->gateway_response)->toBe('سفارش پرداخت نشده یا ناموفق بوده است.')
        ->and($order->refresh()->payment_status->value)->toBe('unpaid')
        ->and($order->status->value)->toBe('pending');
});

test('an order is fulfilled through the local gateway', function () {
    $user = User::factory()->create();
    $product = Product::factory()->followers()->withTiers()->create();

    $order = Order::factory()->for($user)->for($product)->create([
        'quantity' => 5000,
        'unit_price' => 120,
        'total_price' => 600,
    ]);

    $response = $this->actingAs($user)
        ->post('https://likeshow.test/payment/start/'.$order->id);

    $response->assertOk();
    expect(preg_match('/transactionId=(\d+)/', (string) $response->getContent(), $matches))
        ->toBe(1);

    $transactionId = $matches[1];

    $callback = $this->get(
        'https://likeshow.test/payment/callback?transactionId='.$transactionId,
    );

    $callback->assertRedirect();
    expect($callback->headers->get('Location'))->toContain('/payment/result/');
    $callback->assertSessionHas('success');

    $order->refresh();
    expect($order->payment_status->value)->toBe('paid')
        ->and($order->status->value)->toBe('processing')
        ->and($order->paid_at)->not->toBeNull();

    $payment = Payment::query()->where('order_id', $order->id)->sole();
    expect($payment->status->value)->toBe('success')
        ->and($payment->reference_id)->toBe($transactionId)
        ->and($payment->amount)->toBe(600);

    // Replaying the same callback must never double-fulfill the order.
    $this->get('https://likeshow.test/payment/callback?transactionId='.$transactionId)
        ->assertRedirect();

    expect($order->refresh()->payment_status->value)->toBe('paid')
        ->and(Payment::query()->count())->toBe(1);
});

test('a canceled gateway callback fails the payment and keeps the order pending', function () {
    $user = User::factory()->create();
    $product = Product::factory()->followers()->withTiers()->create();

    $order = Order::factory()->for($user)->for($product)->create([
        'quantity' => 5000,
        'unit_price' => 120,
        'total_price' => 600,
    ]);

    $response = $this->actingAs($user)
        ->post('https://likeshow.test/payment/start/'.$order->id);

    $response->assertOk();
    expect(preg_match('/transactionId=(\d+)/', (string) $response->getContent(), $matches))
        ->toBe(1);

    $this->get(
        'https://likeshow.test/payment/callback?transactionId='.$matches[1].'&cancel=true',
    )->assertSessionHas('error');

    $payment = Payment::query()->where('order_id', $order->id)->sole();
    expect($payment->status->value)->toBeIn(['failed', 'canceled']);

    $order->refresh();
    expect($order->payment_status->value)->toBe('unpaid')
        ->and($order->status->value)->toBe('pending');
});

test('orders cannot be paid or reviewed by other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get('https://likeshow.test/payment/review/'.$order->id)
        ->assertForbidden();

    $this->actingAs($intruder)
        ->post('https://likeshow.test/payment/start/'.$order->id)
        ->assertForbidden();

    $this->actingAs($intruder)
        ->get('https://likeshow.test/payment/result/'.$order->id)
        ->assertForbidden();
});

test('unpaid orders cannot start a second payment', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->completed()->create();

    $this->actingAs($user)
        ->post('https://likeshow.test/payment/start/'.$order->id)
        ->assertNotFound();
});
