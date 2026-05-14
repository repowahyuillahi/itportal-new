<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Reject authenticated users (e.g. /login should bounce to /dashboard).
 */
final class GuestMiddleware
{
    public function handle(Request $request, callable $next): string
    {
        if (Auth::check()) {
            return Response::redirect('/dashboard');
        }
        return (string) $next($request);
    }
}
