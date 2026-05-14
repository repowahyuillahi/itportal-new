<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Dashboard placeholder. Phase 6 will load monthly summary cards here.
 */
final class DashboardController
{
    public function index(Request $request): string
    {
        $user = Auth::user() ?? [];
        return Response::html(View::render('dashboard/index', [
            'title' => 'Dashboard - ITPortal',
            'user' => $user,
        ]));
    }
}
