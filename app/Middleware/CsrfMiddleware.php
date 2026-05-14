<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Verify the CSRF token on POST requests.
 *
 * Tokens are read from `_csrf` form field or the `X-CSRF-Token` header.
 */
final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): string
    {
        if ($request->isPost()) {
            $token = (string) ($request->post('_csrf') ?? '');
            if ($token === '') {
                $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            }
            if (!Csrf::verify($token)) {
                Session::flash('error', 'Permintaan ditolak (CSRF token tidak valid).');
                return Response::errorPage(419, '419 - CSRF Mismatch',
                    'Permintaan ditolak karena token keamanan (CSRF) tidak valid. ' .
                    'Coba muat ulang halaman lalu kirim form lagi.');
            }
        }
        return (string) $next($request);
    }
}
