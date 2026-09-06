<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    config(['payment.default' => 'local']);
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
