<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renders a PHP template from resources/views.
 *
 * Usage:
 *   return View::render('auth/login', ['title' => 'Login']);
 *
 * Templates use plain PHP. Layout wrapping is opt-in via the `$layout`
 * variable inside the template (set via the View::layout helper inside the
 * view if needed) or by rendering through a layout file directly.
 */
final class View
{
    private static string $viewsPath = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderRaw($view, $data);
        if ($layout === null) {
            return $content;
        }
        $title = $data['title'] ?? 'ITPortal';
        return self::renderRaw($layout, array_merge($data, [
            'content' => $content,
            'title' => $title,
        ]));
    }

    /** @param array<string, mixed> $data */
    public static function renderRaw(string $view, array $data = []): string
    {
        $file = self::$viewsPath . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $view ($file)");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        /** @psalm-suppress UnresolvableInclude */
        include $file;
        return (string) ob_get_clean();
    }
}
