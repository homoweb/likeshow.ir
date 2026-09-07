<?php

use App\Http\Middleware\SetFramingPolicy;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

covers(SetFramingPolicy::class);

test('web responses allow the enamad seal origins to frame the site', function () {
    Route::get('/framing-probe', fn (): string => 'ok')
        ->middleware(SetFramingPolicy::class);

    get('/framing-probe')
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://*.enamad.ir https://enamad.ir");
});

test('the frame-ancestors allow-list stays configurable per environment', function () {
    config(['likeshow.frame_ancestors' => 'https://shop.example, https://enamad.ir']);

    Route::get('/framing-probe', fn (): string => 'ok')
        ->middleware(SetFramingPolicy::class);

    get('/framing-probe')
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://shop.example https://enamad.ir");
});

test('main site pages are framed by the enamad verification page', function () {
    get(route('main.home'))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://*.enamad.ir https://enamad.ir");
});
