<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

/**
 * Auth controller. CSRF is enforced by CsrfMiddleware on POST routes.
 */
final class AuthController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
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
