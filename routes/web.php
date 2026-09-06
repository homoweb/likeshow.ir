<?php

use App\Http\Controllers\Admin\Auth\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Main\CheckoutController;
use App\Http\Controllers\Main\LandingController;
use App\Http\Controllers\Main\PaymentController;
use App\Http\Controllers\Panel\Auth\AuthController as PanelAuthController;
use App\Http\Controllers\Panel\OrderController as PanelOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Main site — likeshow.test
|--------------------------------------------------------------------------
*/
Route::name('main.')->group(function (): void {
    Route::get('/', [LandingController::class, 'index'])->name('home');

    Route::get('/checkout/{product}', [CheckoutController::class, 'show'])
        ->name('checkout.show');
    Route::post('/checkout/{product}', [CheckoutController::class, 'store'])
        ->name('checkout.store');
    Route::get('/order/resume', [CheckoutController::class, 'resume'])
        ->name('checkout.resume');

    Route::get('/payment/review/{order}', [PaymentController::class, 'review'])
        ->name('payment.review');
    Route::post('/payment/start/{order}', [PaymentController::class, 'start'])
        ->name('payment.start');
    Route::get('/payment/result/{order}', [PaymentController::class, 'result'])
        ->name('payment.result');

    // Logout must be reachable from the main site as well, so users on any
    // page of the app can end their session with a same-origin request.
    Route::post('/logout', [PanelAuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| Payment gateway callback — shared host, outside the section prefixes
|--------------------------------------------------------------------------
*/
Route::any('/payment/callback', [PaymentController::class, 'callback'])
    ->name('payment.callback');

/*
|--------------------------------------------------------------------------
| User panel — /panel
|--------------------------------------------------------------------------
*/
Route::prefix('panel')->name('panel.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [PanelAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PanelAuthController::class, 'login']);
        Route::get('/register', [PanelAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [PanelAuthController::class, 'register']);
    });

    Route::post('/logout', [PanelAuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'active'])->group(function (): void {
        Route::get('/', fn () => redirect()->route('panel.orders.index'))->name('home');
        Route::get('/orders', [PanelOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [PanelOrderController::class, 'show'])->name('orders.show');
    });
});

/*
|--------------------------------------------------------------------------
| Admin panel — /admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login']);
    });

    Route::post('/logout', [AdminAuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'active', 'role:admin'])->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.users.index'))->name('home');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle', [AdminUserController::class, 'toggle'])->name('users.toggle');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/toggle', [AdminProductController::class, 'toggle'])->name('products.toggle');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
    });
});
