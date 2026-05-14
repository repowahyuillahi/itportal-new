<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class HomeController
{
    public function index(Request $request): string
    {
        // Phase 1: just bounce to login or dashboard placeholder.
        if (Auth::check()) {
            return Response::redirect('/dashboard');
        }
        return Response::redirect('/login');
    }
}
