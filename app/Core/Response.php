<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal response helpers. Controllers return strings; the front controller
 * echoes them. Use these helpers for redirects, JSON, and downloads.
 */
final class Response
{
    public static function html(string $body, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        return $body;
    }

    public static function json(mixed $payload, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public static function redirect(string $url, int $status = 302): string
    {
        http_response_code($status);
        header('Location: ' . $url);
        return '';
    }

    public static function notFound(string $body = 'Not Found'): string
    {
        return self::html($body, 404);
    }

    public static function serverError(string $body = 'Server Error'): string
    {
        return self::html($body, 500);
    }

    /**
     * Render a layout-wrapped error page using `errors/generic.php`.
     *
     * @param array<string, mixed> $extra extra view data
     */
    public static function errorPage(int $status, string $heading, string $message, array $extra = []): string
    {
        $data = array_merge([
            'title'   => $status . ' - ITPortal',
            'status'  => $status,
            'heading' => $heading,
            'message' => $message,
        ], $extra);
        return self::html(View::render('errors/generic', $data), $status);
    }
}
