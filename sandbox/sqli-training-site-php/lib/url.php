<?php
/**
 * URL helpers for installations below any URL prefix.
 *
 * The vulnerable and secure apps can be served either as a vhost root
 * (/login) or as folders below a project path (/sqli-training-site-php/vuln/login).
 */

function app_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(dirname($script), '/');
    $base = ($dir === '' || $dir === '.' || $dir === '/') ? '' : $dir;
    return $base;
}

function project_base_path(): string
{
    $base = app_base_path();
    if ($base === '') {
        return '';
    }

    $project = rtrim(dirname($base), '/');
    return ($project === '' || $project === '.' || $project === '/') ? '' : $project;
}

function app_url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return app_base_path() . $path;
}

function asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    return project_base_path() . '/static' . $path;
}

function current_app_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = app_base_path();

    if ($base !== '' && $path === $base) {
        return '/';
    }
    if ($base !== '' && str_starts_with($path, $base . '/')) {
        $path = substr($path, strlen($base));
    }

    return $path === '' ? '/' : $path;
}
