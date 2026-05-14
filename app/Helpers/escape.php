<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;

if (!function_exists('e')) {
    /** Escape string for safe HTML output. */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $value = null): ?string
    {
        return Session::flash($key, $value);
    }
}

if (!function_exists('old')) {
    /** Retrieve old form input from flash storage. */
    function old(string $key, ?string $default = null): ?string
    {
        $bag = Session::flash('_old');
        if ($bag === null) {
            return $default;
        }
        $data = json_decode($bag, true);
        return is_array($data) && array_key_exists($key, $data) ? (string) $data[$key] : $default;
    }
}
