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
use App\Controllers\DealerController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\ItemController;
use App\Controllers\ReportController;
use App\Controllers\TicketController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;

$itAdmin = static fn() => RoleMiddleware::only(['admin', 'it_staff']);
$adminOnly = static fn() => RoleMiddleware::only(['admin']);

// Public routes.
$router->get('/', [HomeController::class, 'index']);
$router->get('/health', [HealthController::class, 'index']);

// Guest-only.
$router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class, GuestMiddleware::class]);

// Authenticated routes.
$router->post('/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

// Tickets (Phase 4).
$router->get('/tickets',                 [TicketController::class, 'index'],  [AuthMiddleware::class]);
$router->get('/tickets/create',          [TicketController::class, 'create'], [AuthMiddleware::class, $itAdmin()]);
$router->post('/tickets',                [TicketController::class, 'store'],  [CsrfMiddleware::class, AuthMiddleware::class, $itAdmin()]);
$router->get('/tickets/{id}',            [TicketController::class, 'show'],   [AuthMiddleware::class]);
$router->get('/tickets/{id}/edit',       [TicketController::class, 'edit'],   [AuthMiddleware::class, $itAdmin()]);
$router->post('/tickets/{id}',           [TicketController::class, 'update'], [CsrfMiddleware::class, AuthMiddleware::class, $itAdmin()]);
$router->post('/tickets/{id}/close',     [TicketController::class, 'close'],  [CsrfMiddleware::class, AuthMiddleware::class, $itAdmin()]);

// Master data: Dealers (Phase 5). Read = any user, mutate = admin only.
$router->get('/dealers',                [DealerController::class, 'index'],        [AuthMiddleware::class]);
$router->get('/dealers/create',         [DealerController::class, 'create'],       [AuthMiddleware::class, $adminOnly()]);
$router->post('/dealers',               [DealerController::class, 'store'],        [CsrfMiddleware::class, AuthMiddleware::class, $adminOnly()]);
$router->get('/dealers/{id}/edit',      [DealerController::class, 'edit'],         [AuthMiddleware::class, $adminOnly()]);
$router->post('/dealers/{id}',          [DealerController::class, 'update'],       [CsrfMiddleware::class, AuthMiddleware::class, $adminOnly()]);
$router->post('/dealers/{id}/status',   [DealerController::class, 'toggleStatus'], [CsrfMiddleware::class, AuthMiddleware::class, $adminOnly()]);

// Master data: Items (Phase 5). Read = any user, mutate = admin only.
$router->get('/items',                  [ItemController::class, 'index'],          [AuthMiddleware::class]);
$router->get('/items/create',           [ItemController::class, 'create'],         [AuthMiddleware::class, $adminOnly()]);
$router->post('/items',                 [ItemController::class, 'store'],          [CsrfMiddleware::class, AuthMiddleware::class, $adminOnly()]);
$router->get('/items/{id}/edit',        [ItemController::class, 'edit'],           [AuthMiddleware::class, $adminOnly()]);
$router->post('/items/{id}',            [ItemController::class, 'update'],         [CsrfMiddleware::class, AuthMiddleware::class, $adminOnly()]);
$router->post('/items/{id}/status',     [ItemController::class, 'toggleStatus'],   [CsrfMiddleware::class, AuthMiddleware::class, $adminOnly()]);

// Reports (Phase 6 preview only). Export buttons placeholder; Phase 7 activates them.
$router->get('/reports/monthly', [ReportController::class, 'monthly'], [AuthMiddleware::class]);

// NOTE: Phase 7 will add /exports/monthly/{xlsx,pdf}.
