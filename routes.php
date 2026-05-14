<?php

declare(strict_types=1);

/**
 * Route definitions.
 *
 * `$router` is provided by bootstrap.php. Keep this file declarative; no
 * logic here.
 */

/** @var App\Core\Router $router */

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

// Public routes.
$router->get('/', [HomeController::class, 'index']);
$router->get('/health', [HealthController::class, 'index']);

// Guest-only.
$router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class, GuestMiddleware::class]);

// Authenticated routes (Phase 3 onwards).
$router->post('/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

// NOTE: Phase 4-6 will add /tickets, /dealers, /items, /reports/monthly,
// /exports/monthly/* with [AuthMiddleware::class] (and RoleMiddleware where
// docs/routes-and-controllers.md requires admin/it_staff).
