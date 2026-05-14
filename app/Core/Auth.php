<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Auth helper. Session-backed identity for V1. Real login wiring lands in
 * Phase 3 (AuthController + UserRepository).
 */
final class Auth
{
    private const KEY = '_auth_user';

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $u = $_SESSION[self::KEY] ?? null;
        return is_array($u) ? $u : null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u && isset($u['id']) ? (int) $u['id'] : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user): void
    {
        $_SESSION[self::KEY] = $user;
        Session::regenerate();
        Csrf::rotate();
    }

    public static function logout(): void
    {
        unset($_SESSION[self::KEY]);
        Session::regenerate();
    }

    /** @param array<int, string> $roles */
    public static function hasAnyRole(array $roles): bool
    {
        $r = self::role();
        return $r !== null && in_array($r, $roles, true);
    }
}
