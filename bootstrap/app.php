<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Send "guest" and "auth" redirects to the right section. Main, panel
        // and admin share one host, so every redirect is same-origin by
        // construction and is chosen by the request's path prefix.
        $middleware->redirectGuestsTo(function (Request $request) {
            $adminPrefix = (string) config('likeshow.admin_prefix');
            $panelPrefix = (string) config('likeshow.panel_prefix');

            if ($request->is($adminPrefix, "$adminPrefix/*")) {
                return route('admin.login');
            }

            if ($request->is($panelPrefix, "$panelPrefix/*")) {
                return route('panel.login');
            }

            // The main site has no login page of its own; keep its guest
            // redirects on the landing page instead of bouncing XHRs to the
            // panel.
            return route('main.home');
        });

        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is(
                (string) config('likeshow.admin_prefix'),
                (string) config('likeshow.admin_prefix').'/*',
            )
                ? route('admin.users.index')
                : route('panel.orders.index'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
