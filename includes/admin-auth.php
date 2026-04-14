<?php
/**
 * ChemHeaven — Admin Authentication Guard
 *
 * Include at the top of every admin page (after send_security_headers +
 * session_secure_start have been called).
 *
 * Security features:
 *  - Bcrypt password verification (password_hash / password_verify)
 *  - Rate-limiting: tracks failed attempts in session; locks for
 *    ADMIN_LOCKOUT_SECS after ADMIN_MAX_ATTEMPTS failures
 *  - Session fixed-user binding (IP + UA hash stored on login)
 *  - Redirects to login page if not authenticated
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/** Return true if the current session has a valid admin login. */
function admin_is_logged_in(): bool
{
    if (empty($_SESSION[ADMIN_SESSION_KEY]['user_id'])) {
        return false;
    }

    // Bind session to the browser fingerprint set at login time.
    $expectedFp = $_SESSION[ADMIN_SESSION_KEY]['fp'] ?? '';
    if ($expectedFp === '' || !hash_equals($expectedFp, admin_fingerprint())) {
        return false;
    }

    return true;
}

/** Compute a lightweight, non-tracking session binding fingerprint. */
function admin_fingerprint(): string
{
    // Use only server-side-visible values (no JS needed).
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR']     ?? '';
    return hash('sha256', $ip . '|' . $ua . '|' . session_id());
}

/**
 * Require admin login. Redirects to login page if not authenticated.
 * Call this at the top of every admin page.
 */
function admin_require_auth(): void
{
    if (!admin_is_logged_in()) {
        safe_redirect('/admin/login.php');
    }
}

/**
 * Attempt to log in with given credentials.
 * Returns ['ok' => true, 'user' => [...]] on success.
 * Returns ['ok' => false, 'error' => '...'] on failure.
 */
function admin_attempt_login(string $username, string $password): array
{
    // ── Rate limiting ─────────────────────────────────────────────────────────
    $attempts  = (int)($_SESSION['_admin_attempts']  ?? 0);
    $lockUntil = (int)($_SESSION['_admin_lock_until'] ?? 0);

    if ($lockUntil > time()) {
        $wait = ceil(($lockUntil - time()) / 60);
        return ['ok' => false, 'error' => "Too many failed attempts. Try again in {$wait} minute(s).", 'locked' => true];
    }

    // ── Fetch user ────────────────────────────────────────────────────────────
    $stmt = db()->prepare(
        'SELECT id, username, password_hash FROM admin_users WHERE username = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    // Verify password (always run verify to prevent timing attacks even on miss)
    $hash = $user['password_hash'] ?? '$2y$12$invalid.hash.to.prevent.timing.attacks.padding';
    if (!$user || !password_verify($password, $hash)) {
        $attempts++;
        $_SESSION['_admin_attempts'] = $attempts;
        if ($attempts >= ADMIN_MAX_ATTEMPTS) {
            $_SESSION['_admin_lock_until'] = time() + ADMIN_LOCKOUT_SECS;
            $_SESSION['_admin_attempts']   = 0;
            return ['ok' => false, 'error' => 'Too many failed attempts. Account locked for 15 minutes.', 'locked' => true];
        }
        $remaining = ADMIN_MAX_ATTEMPTS - $attempts;
        return ['ok' => false, 'error' => "Invalid credentials. {$remaining} attempt(s) remaining."];
    }

    // ── Success ───────────────────────────────────────────────────────────────
    // Clear rate limit state.
    unset($_SESSION['_admin_attempts'], $_SESSION['_admin_lock_until']);

    // Rotate session ID to prevent fixation.
    session_regenerate_id(true);

    $_SESSION[ADMIN_SESSION_KEY] = [
        'user_id'  => (int)$user['id'],
        'username' => $user['username'],
        'fp'       => admin_fingerprint(),
    ];

    // Record last login (non-critical, ignore failure).
    try {
        db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id')
             ->execute([':id' => $user['id']]);
    } catch (\Throwable) {}

    // Rehash if bcrypt cost has changed.
    if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id')
             ->execute([':h' => $newHash, ':id' => $user['id']]);
    }

    return ['ok' => true, 'user' => $user];
}

/** Log out the current admin. */
function admin_logout(): void
{
    unset($_SESSION[ADMIN_SESSION_KEY]);
    session_regenerate_id(true);
}

/** Return the currently logged-in admin's data from session. */
function admin_current(): array
{
    return $_SESSION[ADMIN_SESSION_KEY] ?? [];
}
