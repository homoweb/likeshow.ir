<?php

use Illuminate\Support\Env;

/*
|--------------------------------------------------------------------------
| LikeShow Application Configuration
|--------------------------------------------------------------------------
|
| Routing paths and business constants of the platform. The application is
| served from a single domain: the main site at /, the user panel under
| /panel and the admin panel under /admin. Values are read from the
| environment so local (.test) and production (.ir) environments can be
| switched without touching the code.
|
*/

$scheme = Env::get('LS_SCHEME', 'https');
$mainDomain = Env::get('LS_MAIN_DOMAIN', 'likeshow.test');
$panelPrefix = Env::get('LS_PANEL_PREFIX', 'panel');
$adminPrefix = Env::get('LS_ADMIN_PREFIX', 'admin');

return [

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */

    'scheme' => $scheme,

    'main_domain' => $mainDomain,
    'panel_prefix' => $panelPrefix,
    'admin_prefix' => $adminPrefix,

    /*
    |--------------------------------------------------------------------------
    | Absolute base URLs (used for cross-section redirects and callbacks)
    |--------------------------------------------------------------------------
    */

    'main_url' => Env::get('LS_MAIN_URL', "$scheme://$mainDomain"),
    'panel_url' => Env::get('LS_PANEL_URL', "$scheme://$mainDomain/$panelPrefix"),
    'admin_url' => Env::get('LS_ADMIN_URL', "$scheme://$mainDomain/$adminPrefix"),

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    'order' => [
        'number_prefix' => 'LS',
        'per_page' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */

    'currency' => Env::get('LS_CURRENCY', 'IRT'),
];
