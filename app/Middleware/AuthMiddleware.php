<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Require an authenticated session. Redirects to /login when missing.
 */
final class AuthMiddleware
{
    public function handle(Request $request, callable $next): string
    {
        if (!Auth::check()) {
            Session::flash('error', 'Silakan login terlebih dahulu.');
            return Response::redirect('/login');
        }
        return (string) $next($request);
    }
}
