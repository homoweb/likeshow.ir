<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'siteUrl' => config('likeshow.main_url'),
            // All section targets are generated server-side from named
            // routes, so the frontend never has to concatenate URL fragments.
            'urls' => [
                'main' => [
                    'home' => route('main.home'),
                ],
                'panel' => [
                    'home' => route('panel.home'),
                    'orders' => route('panel.orders.index'),
                    'login' => route('panel.login'),
                    'register' => route('panel.register'),
                    'logout' => route('panel.logout'),
                ],
                'admin' => [
                    'home' => route('admin.home'),
                    'login' => route('admin.login'),
                    'logout' => route('admin.logout'),
                ],
            ],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'is_admin' => $request->user()->hasRole('admin'),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
