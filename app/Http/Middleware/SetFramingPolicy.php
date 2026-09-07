<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetFramingPolicy
{
    /**
     * Declare which origins may embed the site in a frame (CSP
     * frame-ancestors). eNAMAD displays the site inside an iframe on its
     * verification page, so its origins have to be allowed. X-Frame-Options
     * cannot allow-list origins and browsers ignore it once frame-ancestors
     * is present, which also supersedes a same-origin-only header set at the
     * web server (e.g. nginx).
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $origins = collect(explode(',', (string) config('likeshow.frame_ancestors', '')))
            ->map(fn (string $origin): string => trim($origin))
            ->filter()
            ->unique()
            ->values()
            ->implode(' ');

        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' {$origins}");

        return $response;
    }
}
