<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->be($this->admin);
});

test('admins can list, create, toggle and delete users', function () {
    $this->get('https://likeshow.test/admin/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 1));

    $this->post('https://likeshow.test/admin/users', [
        'name' => 'کاربر تازه',
        'email' => 'new@example.com',
        'password' => 'password123',
        'is_active' => true,
        'role' => 'user',
    ])->assertRedirect()->assertSessionHas('success');

    $created = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect($created->hasRole('user'))->toBeTrue()
        ->and($created->is_active)->toBeTrue();

    $target = User::factory()->create();

    $this->patch('https://likeshow.test/admin/users/'.$target->id.'/toggle')
        ->assertRedirect()->assertSessionHas('success');
    expect($target->refresh()->is_active)->toBeFalse();

    // An admin can never deactivate or delete their own account.
    $this->patch('https://likeshow.test/admin/users/'.$this->admin->id.'/toggle')
        ->assertSessionHas('error');
    expect($this->admin->refresh()->is_active)->toBeTrue();

    $this->delete('https://likeshow.test/admin/users/'.$target->id)
        ->assertSessionHas('success');
    expect(User::query()->find($target->id))->toBeNull();

    $this->delete('https://likeshow.test/admin/users/'.$this->admin->id)
        ->assertSessionHas('error');
    expect(User::query()->find($this->admin->id))->not->toBeNull();
});

test('admins can update users including optional password changes', function () {
    $target = User::factory()->create();

    $this->put('https://likeshow.test/admin/users/'.$target->id, [
        'name' => 'نام تازه',
        'email' => 'renamed@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
        'is_active' => false,
        'role' => 'admin',
    ])->assertRedirect()->assertSessionHas('success');

    $target->refresh();
    expect($target->name)->toBe('نام تازه')
        ->and($target->email)->toBe('renamed@example.com')
        ->and($target->is_active)->toBeFalse()
        ->and($target->hasRole('admin'))->toBeTrue()
        ->and(Hash::check('newpassword123', $target->password))->toBeTrue();
});

test('admins can manage products and their price tiers', function () {
    $payload = [
        'title' => 'فالوور اینستاگرام',
        'description' => 'توضیحات سرویس',
        'platform' => 'instagram',
        'type' => 'followers',
        'min_quantity' => 1000,
        'max_quantity' => 1000000,
        'step_quantity' => 1000,
        'base_price' => 90000,
        'is_active' => true,
        'prices' => [
            ['min_quantity' => 1000, 'max_quantity' => 5000, 'price' => 90000],
            ['min_quantity' => 5001, 'max_quantity' => 1000000, 'price' => 80000],
        ],
    ];

    $this->post('https://likeshow.test/admin/products', $payload)
        ->assertRedirect()->assertSessionHas('success');

    $product = Product::query()->where('title', 'فالوور اینستاگرام')->firstOrFail();
    expect($product->prices()->count())->toBe(2)
        ->and($product->is_active)->toBeTrue();

    // Duplicate platform+type is rejected.
    $this->post('https://likeshow.test/admin/products', $payload)
        ->assertSessionHasErrors(['platform']);

    $payload['title'] = 'فالوور اینستاگرام پرو';
    $payload['prices'] = [
        ['min_quantity' => 1000, 'max_quantity' => 1000000, 'price' => 70000],
    ];

    $this->put('https://likeshow.test/admin/products/'.$product->id, $payload)
        ->assertRedirect()->assertSessionHas('success');

    $product->refresh();
    expect($product->title)->toBe('فالوور اینستاگرام پرو')
        ->and($product->prices()->count())->toBe(1);

    $this->patch('https://likeshow.test/admin/products/'.$product->id.'/toggle')
        ->assertSessionHas('success');
    expect($product->refresh()->is_active)->toBeFalse();

    // A product holding orders is deactivated instead of deleted.
    Order::factory()->for($product)->create();

    $this->delete('https://likeshow.test/admin/products/'.$product->id)
        ->assertSessionHas('info');
    expect(Product::query()->find($product->id))->not->toBeNull()
        ->and($product->refresh()->is_active)->toBeFalse();

    // A product without orders is deleted outright.
    $plain = Product::factory()->likes()->create();
    $this->delete('https://likeshow.test/admin/products/'.$plain->id)
        ->assertSessionHas('success');
    expect(Product::query()->find($plain->id))->toBeNull();
});

test('admins can advance order status', function () {
    $order = Order::factory()->create();

    $this->patch('https://likeshow.test/admin/orders/'.$order->id.'/status', [
        'status' => 'completed',
    ])->assertRedirect()->assertSessionHas('success');

    $order->refresh();
    expect($order->status->value)->toBe('completed')
        ->and($order->payment_status->value)->toBe('paid');

    $this->patch('https://likeshow.test/admin/orders/'.$order->id.'/status', [
        'status' => 'pending',
    ])->assertSessionHasErrors(['status']);
});

test('non-admins and guests are kept out of the admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get('https://likeshow.test/admin/users')
        ->assertForbidden();

    // The login route is guest-only, so drop the session's user first.
    $this->app['auth']->guard()->logout();

    $this->post('https://likeshow.test/admin/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email']);
    $this->assertGuest();

    $response = $this->get('https://likeshow.test/admin/users');
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login');
});
