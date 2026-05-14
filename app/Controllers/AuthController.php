<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use App\Services\LoginThrottle;

/**
 * Auth controller. CSRF is enforced by CsrfMiddleware on POST routes.
 *
 * Rate limiting: `LoginThrottle` blocks an attempt once
 * `audit_logs.action = 'login.failed'` reaches a per-(IP,email) or
 * per-IP threshold within a sliding window (defaults: 5/email, 20/IP,
 * 15 minutes). The block reads from `audit_logs` so no extra table is
 * needed.
 */
final class AuthController
{
    private AuthService $auth;
    private LoginThrottle $throttle;

    public function __construct(?AuthService $auth = null, ?LoginThrottle $throttle = null)
    {
        $this->auth = $auth ?? new AuthService();
        $this->throttle = $throttle ?? new LoginThrottle();
    }

    public function showLogin(Request $request): string
    {
        return Response::html(View::render('auth/login', [
            'title' => 'Login - ITPortal',
        ]));
    }

    public function login(Request $request): string
    {
        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('password', '');

        // Throttle: reject loud-and-clear before any password_verify so
        // the cost of brute-forcing remains low for legitimate users and
        // high for attackers (the failed-attempt history is already in
        // audit_logs from prior unsuccessful tries).
        $gate = $this->throttle->check($request, $email);
        if ($gate['blocked']) {
            header('Retry-After: ' . (string) $gate['retry_after_seconds']);
            return Response::errorPage(
                429,
                'Terlalu Banyak Percobaan Login',
                'Akun atau alamat IP ini sementara di-blokir karena terlalu banyak percobaan login gagal. '
                . 'Coba lagi dalam ' . max(1, (int) ceil($gate['retry_after_seconds'] / 60)) . ' menit.'
            );
        }

        $result = $this->auth->attemptLogin($email, $password, $request);

        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? 'Login gagal.');
            // Preserve email (NOT password) for the next render.
            Session::flash('_old', json_encode(['email' => $email], JSON_UNESCAPED_UNICODE) ?: '{}');
            return Response::redirect('/login');
        }

        Session::flash('success', 'Selamat datang, ' . ($result['user']['name'] ?? '') . '.');
        return Response::redirect('/dashboard');
    }

    public function logout(Request $request): string
    {
        $this->auth->logout($request);
        Session::flash('success', 'Anda sudah logout.');
        return Response::redirect('/login');
    }
}
