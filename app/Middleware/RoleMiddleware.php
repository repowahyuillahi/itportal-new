<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Allow only specific roles. Use via `RoleMiddleware::only(['admin'])`
 * inside the route definition.
 *
 * Assumes AuthMiddleware ran first, so we already have a logged-in user.
 */
final class RoleMiddleware
{
    /** @var array<int, string> */
    private array $allowed;

    /** @param array<int, string> $roles */
    public function __construct(array $roles)
    {
        $this->allowed = $roles;
    }

    /** @param array<int, string> $roles */
    public static function only(array $roles): self
    {
        return new self($roles);
    }

    public function handle(Request $request, callable $next): string
    {
        if (!Auth::check()) {
            return Response::redirect('/login');
        }
        if (!Auth::hasAnyRole($this->allowed)) {
            Session::flash('error', 'Akses ditolak. Role kamu tidak diizinkan untuk halaman ini.');
            return Response::errorPage(403, '403 Forbidden',
                'Akses ditolak. Role akun kamu tidak diizinkan untuk halaman ini.');
        }
        return (string) $next($request);
    }
}
