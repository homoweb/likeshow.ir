<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Pin the configured main URL scheme for deterministic assertions.
    config(['likeshow.main_url' => 'https://likeshow.test']);
});

test('guest checkout stores a draft and redirects to the panel login', function () {
    $product = Product::factory()->followers()->withTiers()->create();

    $response = $this->post('https://likeshow.test/checkout/'.$product->id, [
        'quantity' => 5000,
        'target_username' => 'my_page',
    ]);

    $response->assertRedirect('https://likeshow.test/panel/login');
    $response->assertSessionHas('checkout_draft');
    $response->assertSessionHas('info');

    expect(Order::query()->count())->toBe(0);
});

test('guest checkout answers inertia requests with x-inertia-location instead of a plain 302', function () {
    $product = Product::factory()->followers()->withTiers()->create();

    // The frontend posts the checkout form via Inertia (XHR). A redirect on
    // that request must reach the client as 409 + X-Inertia-Location so it
    // performs a full page visit to the panel login instead of a fragile
    // XHR redirect hop.
    $response = $this->post('https://likeshow.test/checkout/'.$product->id, [
        'quantity' => 5000,
        'target_username' => 'my_page',
    ], ['X-Inertia' => 'true']);

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))
        ->toBe('https://likeshow.test/panel/login');

    $response->assertSessionHas('checkout_draft');
    $response->assertSessionHas('info');

    expect(Order::query()->count())->toBe(0);
});

test('guest checkout resumes after registering on the panel', function () {
    $this->seed(PermissionSeeder::class);

    $product = Product::factory()->followers()->withTiers()->create();

    $this->post('https://likeshow.test/checkout/'.$product->id, [
        'quantity' => 5000,
        'target_username' => 'my_page',
    ]);

    $response = $this->post('https://likeshow.test/panel/register', [
        'name' => 'علی رضایی',
        'email' => 'ali@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('https://likeshow.test/order/resume');

    $resume = $this->get('https://likeshow.test/order/resume');

    $resume->assertRedirect();
    expect($resume->headers->get('Location'))->toContain('/payment/review/');
    $resume->assertSessionMissing('checkout_draft');

    $order = Order::query()->sole();
    $user = User::query()->where('email', 'ali@example.com')->firstOrFail();

    expect($order->user_id)->toBe($user->getKey())
        ->and($user->hasRole('user'))->toBeTrue()
        ->and($order->quantity)->toBe(5000)
        ->and($order->target_username)->toBe('my_page')
        // 5000 units fall into the [1000, 5000] tier priced at 120 per 1000.
        ->and($order->unit_price)->toBe(120)
        ->and($order->total_price)->toBe(600)
        ->and($order->status->value)->toBe('pending')
        ->and($order->payment_status->value)->toBe('unpaid');
});

test('login resumes a draft for inertia requests via x-inertia-location instead of a cross-origin 302', function () {
    $user = User::factory()->create();
    $product = Product::factory()->followers()->withTiers()->create();

    $this->post('https://likeshow.test/checkout/'.$product->id, [
        'quantity' => 20000,
        'target_username' => 'another_page',
    ]);

    // The panel login form posts via Inertia (XHR). The draft resume must
    // be a full page visit, so the response carries 409 + X-Inertia-Location
    // instead of a plain redirect.
    $response = $this->post('https://likeshow.test/panel/login', [
        'email' => $user->email,
        'password' => 'password',
    ], ['X-Inertia' => 'true']);

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))
        ->toBe('https://likeshow.test/order/resume');
});

test('login ignores intended urls outside the panel', function () {
    $user = User::factory()->create();

    // Simulate a stale intended URL left behind by an earlier guest redirect
    // on the main site; it points outside the panel, so the login response
    // must ignore it and land on the panel orders list.
    session(['url.intended' => 'https://likeshow.test/checkout/1']);

    $response = $this->post('https://likeshow.test/panel/login', [
        'email' => $user->email,
        'password' => 'password',
    ], ['X-Inertia' => 'true']);

    $response->assertRedirect('https://likeshow.test/panel/orders');
});

test('guest checkout resumes after logging in on the panel', function () {
    $user = User::factory()->create();
    $product = Product::factory()->followers()->withTiers()->create();

    $this->post('https://likeshow.test/checkout/'.$product->id, [
        'quantity' => 20000,
        'target_username' => 'another_page',
    ]);

    $response = $this->post('https://likeshow.test/panel/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('https://likeshow.test/order/resume');

    $this->get('https://likeshow.test/order/resume');

    $order = Order::query()->sole();

    expect($order->user_id)->toBe($user->getKey())
        // 20000 units fall into the [5001, 20000] tier priced at 100 per 1000.
        ->and($order->unit_price)->toBe(100)
        ->and($order->total_price)->toBe(2000);
});

test('checkout rejects invalid usernames and quantities', function () {
    $product = Product::factory()->followers()->withTiers()->create();

    $this->post('https://likeshow.test/checkout/'.$product->id, [
        'quantity' => 5000,
        'target_username' => 'نامشخص!',
    ])->assertSessionHasErrors(['target_username']);

    $this->post('https://likeshow.test/checkout/'.$product->id, [
        'quantity' => 500,
        'target_username' => 'my_page',
    ])->assertSessionHasErrors(['quantity']);

    expect(Order::query()->count())->toBe(0);
});

test('the checkout page renders for active products only', function () {
    $product = Product::factory()->followers()->withTiers()->create();
    $inactive = Product::factory()->likes()->inactive()->create();

    $this->get('https://likeshow.test/checkout/'.$product->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Main/Checkout')
            ->where('product.id', $product->id)
            ->count('product.prices', 3));

    $this->get('https://likeshow.test/checkout/'.$inactive->id)
        ->assertNotFound();
});
