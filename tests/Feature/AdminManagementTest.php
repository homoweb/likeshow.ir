<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPlatform;
use App\Models\ProductType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->be($this->admin);
});

test('admins can manage product platforms and service types dynamically', function () {
    // The taxonomy screen lists the seeded defaults.
    $this->get('https://likeshow.test/admin/settings/taxonomies')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Taxonomies')
            ->has('platforms', 1)
            ->has('types', 2));

    // A new platform immediately appears in the product form options.
    $this->post('https://likeshow.test/admin/settings/platforms', [
        'slug' => 'telegram',
        'name' => 'تلگرام',
        'sort_order' => 2,
    ])->assertRedirect()->assertSessionHas('success');

    $this->post('https://likeshow.test/admin/settings/types', [
        'slug' => 'views',
        'name' => 'بازدید',
        'sort_order' => 3,
    ])->assertRedirect()->assertSessionHas('success');

    $telegram = ProductPlatform::query()->where('slug', 'telegram')->firstOrFail();
    $views = ProductType::query()->where('slug', 'views')->firstOrFail();

    $this->get('https://likeshow.test/admin/products/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Create')
            ->where('platforms.1.value', 'telegram')
            ->where('types.2.value', 'views'));

    // Duplicate slugs and names are rejected for both taxonomies.
    $this->post('https://likeshow.test/admin/settings/platforms', [
        'slug' => 'telegram',
        'name' => 'تلگرام',
    ])->assertSessionHasErrors(['slug']);

    $this->post('https://likeshow.test/admin/settings/types', [
        'slug' => 'followers',
        'name' => 'تازه',
    ])->assertSessionHasErrors(['slug']);

    // A product can now be created for the new platform + type pair.
    $payload = [
        'title' => 'بازدید تلگرام',
        'platform' => 'telegram',
        'type' => 'views',
        'min_quantity' => 1000,
        'max_quantity' => 100000,
        'step_quantity' => 1000,
        'base_price' => 50000,
        'is_active' => true,
    ];

    $this->post('https://likeshow.test/admin/products', $payload)
        ->assertRedirect()->assertSessionHas('success');

    $product = Product::query()->where('title', 'بازدید تلگرام')->firstOrFail();
    expect($product->product_platform_id)->toBe($telegram->id)
        ->and($product->product_type_id)->toBe($views->id)
        ->and($product->platform?->name)->toBe('تلگرام')
        ->and($product->type?->name)->toBe('بازدید');

    // Taxonomy rows can be renamed, and the change flows into the product.
    $this->put('https://likeshow.test/admin/settings/platforms/'.$telegram->id, [
        'slug' => 'telegram',
        'name' => 'تلگرام فارسی',
    ])->assertRedirect()->assertSessionHas('success');

    expect($product->refresh()->platform?->name)->toBe('تلگرام فارسی');

    // Deactivating a platform removes it from the product form options,
    // but existing products keep working.
    $this->patch('https://likeshow.test/admin/settings/platforms/'.$telegram->id.'/toggle')
        ->assertRedirect()->assertSessionHas('success');

    expect($telegram->refresh()->is_active)->toBeFalse()
        ->and($product->refresh()->platform->is_active)->toBeFalse();

    $this->get('https://likeshow.test/admin/products/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Create')
            ->has('platforms', 1));
});

test('product forms reject taxonomy slugs that do not exist', function () {
    $this->post('https://likeshow.test/admin/products', [
        'title' => 'محصول نامعتبر',
        'platform' => 'tiktok',
        'type' => 'followers',
        'min_quantity' => 1000,
        'max_quantity' => 100000,
        'step_quantity' => 1000,
        'base_price' => 50000,
        'is_active' => true,
    ])->assertSessionHasErrors(['platform']);

    expect(Product::query()->where('title', 'محصول نامعتبر')->exists())->toBeFalse();
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
