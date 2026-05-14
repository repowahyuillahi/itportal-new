<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Loads autoloader, .env, config, and starts the session. Returns a callable
 * that dispatches a Request through the Router (so `public/index.php` stays
 * tiny).
 */

define('APP_BASE_PATH', __DIR__);

// 1. Autoloader: prefer Composer if present, fall back to a built-in PSR-4
// autoloader so the app runs before `composer install`.
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    require __DIR__ . '/app/Core/Autoloader.php';
    $autoloader = new App\Core\Autoloader();
    $autoloader->addNamespace('App', __DIR__ . '/app');
    $autoloader->register();
    // Manually include helper files (Composer would do this via "files" autoload).
    require __DIR__ . '/app/Helpers/escape.php';
    require __DIR__ . '/app/Helpers/date.php';
}

// 2. Environment.
App\Core\Env::load(__DIR__ . '/.env');

// 3. Config.
App\Core\Config::loadDir(__DIR__ . '/config');

// 4. Timezone + error display.
date_default_timezone_set((string) (App\Core\Config::get('app.timezone', 'Asia/Jakarta')));
$debug = (bool) App\Core\Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/php-error.log');
error_reporting(E_ALL);

// 5. Session.
App\Core\Session::start();

// 5b. Security baseline: HTTPS redirect (prod only) + security headers.
//     Done after session start so we can still set headers, but before
//     any controller has a chance to emit body content.
if (PHP_SAPI !== 'cli') {
    if (App\Core\Security::enforceHttpsIfProduction()) {
        // Redirect emitted; nothing more to do for this request.
        return static fn(App\Core\Request $r): string => '';
    }
    App\Core\Security::sendHeaders();
}

// 6. Views.
App\Core\View::setViewsPath(__DIR__ . '/resources/views');

// 7. Router + routes.
$router = new App\Core\Router();
require __DIR__ . '/routes.php';

return function (App\Core\Request $request) use ($router): string {
    return $router->dispatch($request);
};
