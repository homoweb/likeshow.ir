<?php

/*
|--------------------------------------------------------------------------
| LikeShow global helpers
|--------------------------------------------------------------------------
*/

if (! function_exists('ls_url')) {
    /**
     * Build an absolute URL for one of the application sections.
     *
     * The base URL and the given path are normalized so the helper never
     * emits a trailing slash on the base or a duplicated slash between
     * the base URL and the path (e.g. "https://x//login").
     */
    function ls_url(string $domainKey, string $path = ''): string
    {
        $base = rtrim((string) config("likeshow.{$domainKey}_url"), '/');
        $path = trim($path, '/');

        return $path === '' ? $base : "{$base}/{$path}";
    }
}

if (! function_exists('ls_main_url')) {
    /**
     * Absolute URL on the main (landing) site.
     */
    function ls_main_url(string $path = ''): string
    {
        return ls_url('main', $path);
    }
}

if (! function_exists('ls_panel_url')) {
    /**
     * Absolute URL on the user panel (/panel).
     */
    function ls_panel_url(string $path = ''): string
    {
        return ls_url('panel', $path);
    }
}

if (! function_exists('ls_admin_url')) {
    /**
     * Absolute URL on the admin panel (/admin).
     */
    function ls_admin_url(string $path = ''): string
    {
        return ls_url('admin', $path);
    }
}
