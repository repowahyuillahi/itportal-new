<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Request;
use App\Repositories\UserRepository;

/**
 * AuthService - login/logout business logic.
 *
 * Returns a structured result so the controller can decide what to render.
 */
final class AuthService
{
    public const ALLOWED_ROLES = ['admin', 'it_staff', 'manager', 'viewer'];

    private UserRepository $users;
    private AuditService $audit;

    public function __construct(?UserRepository $users = null, ?AuditService $audit = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->audit = $audit ?? new AuditService();
    }

    /**
     * @return array{ok: bool, user?: array<string, mixed>, error?: string}
     */
    public function attemptLogin(string $email, string $password, Request $request): array
    {
        $email = strtolower(trim($email));

        if ($email === '' || $password === '') {
            return ['ok' => false, 'error' => 'Email dan password wajib diisi.'];
        }

        $user = $this->users->findActiveByEmail($email);

        if ($user === null) {
            // Run a dummy hash to keep timing roughly constant.
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuv');
            $this->audit->log(null, 'login.failed', $request, 'user', null, null, [
                'email' => $email,
                'reason' => 'not_found',
            ]);
            return ['ok' => false, 'error' => 'Email atau password salah.'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $this->audit->log((int) $user['id'], 'login.failed', $request, 'user', (string) $user['id'], null, [
                'reason' => 'bad_password',
            ]);
            return ['ok' => false, 'error' => 'Email atau password salah.'];
        }

        if ($user['status'] !== 'active') {
            $this->audit->log((int) $user['id'], 'login.failed', $request, 'user', (string) $user['id'], null, [
                'reason' => 'inactive',
            ]);
            return ['ok' => false, 'error' => 'Akun tidak aktif. Hubungi admin.'];
        }

        if (!in_array($user['role'], self::ALLOWED_ROLES, true)) {
            $this->audit->log((int) $user['id'], 'login.failed', $request, 'user', (string) $user['id'], null, [
                'reason' => 'invalid_role',
            ]);
            return ['ok' => false, 'error' => 'Role tidak valid. Hubungi admin.'];
        }

        // Establish session identity.
        Auth::login([
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ]);

        $this->users->touchLastLogin((int) $user['id']);
        $this->audit->log((int) $user['id'], 'login.success', $request, 'user', (string) $user['id']);

        return [
            'ok' => true,
            'user' => Auth::user() ?? [],
        ];
    }

    public function logout(Request $request): void
    {
        $userId = Auth::id();
        Auth::logout();
        $this->audit->log($userId, 'logout', $request, 'user', $userId === null ? null : (string) $userId);
    }
}
