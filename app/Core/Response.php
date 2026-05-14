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
     * Stream a file from disk as an attachment download.
     *
     * - Sends headers and `readfile()` directly so large files (Excel/PDF
     *   exports) don't have to be loaded into memory.
     * - Returns the empty string so the front controller has nothing else
     *   to echo.
     * - The caller is responsible for picking a safe `$absolutePath`
     *   (never derived from user input).
     */
    public static function download(string $absolutePath, string $filename, string $contentType): string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return self::errorPage(
                500,
                'Gagal Mengunduh',
                'File hasil export tidak ditemukan di server. Silakan coba lagi atau hubungi admin.'
            );
        }
        // Sanitize filename: strip any path separators or control chars.
        $safeName = preg_replace('/[\\\\\\/\\r\\n"]+/', '_', $filename) ?? 'download';
        $size = filesize($absolutePath);
        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        // Flush PHP output buffers so headers + body line up cleanly.
        while (ob_get_level() > 0) { ob_end_clean(); }
        readfile($absolutePath);
        return '';
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
