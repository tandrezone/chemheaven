<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Services;

use PDOException;
use Tandrezone\Chemheaven\Services\Database;

final class AdminAuth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function check(): bool
    {
        self::startSession();

        return !empty($_SESSION['admin_user_id']);
    }

    public static function username(): string
    {
        self::startSession();

        return (string) ($_SESSION['admin_username'] ?? '');
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            $next = urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/admin'));
            header('Location: /admin/login?next=' . $next);
            exit;
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        self::startSession();

        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }

        if (self::isLockedOut($username)) {
            return false;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :u LIMIT 1');
            $stmt->execute(['u' => $username]);
            $user = $stmt->fetch();
        } catch (PDOException) {
            return false;
        }

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            self::recordFailure($username);
            return false;
        }

        self::clearFailures($username);
        $_SESSION['admin_user_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = (string) $user['username'];
        session_regenerate_id(true);

        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        unset($_SESSION['admin_user_id'], $_SESSION['admin_username']);
        session_regenerate_id(true);
    }

    public static function lockoutMessage(string $username): string
    {
        if (!self::isLockedOut($username)) {
            return '';
        }

        $remaining = self::lockoutRemaining($username);

        return 'Too many failed attempts. Try again in ' . max(1, (int) ceil($remaining / 60)) . ' minutes.';
    }

    private static function lockKey(string $username): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return strtolower($username) . '|' . $ip;
    }

    private static function isLockedOut(string $username): bool
    {
        return self::lockoutRemaining($username) > 0;
    }

    private static function lockoutRemaining(string $username): int
    {
        self::startSession();
        $key = self::lockKey($username);
        $failures = $_SESSION['admin_login_failures'][$key] ?? null;

        if (!is_array($failures)) {
            return 0;
        }

        $count = (int) ($failures['count'] ?? 0);
        $lockedUntil = (int) ($failures['locked_until'] ?? 0);

        if ($count < self::MAX_ATTEMPTS || $lockedUntil <= time()) {
            return 0;
        }

        return $lockedUntil - time();
    }

    private static function recordFailure(string $username): void
    {
        self::startSession();
        $key = self::lockKey($username);
        $failures = $_SESSION['admin_login_failures'][$key] ?? ['count' => 0, 'locked_until' => 0];
        $failures['count'] = (int) ($failures['count'] ?? 0) + 1;

        if ($failures['count'] >= self::MAX_ATTEMPTS) {
            $failures['locked_until'] = time() + self::LOCKOUT_SECONDS;
        }

        $_SESSION['admin_login_failures'][$key] = $failures;
    }

    private static function clearFailures(string $username): void
    {
        self::startSession();
        unset($_SESSION['admin_login_failures'][self::lockKey($username)]);
    }
}
