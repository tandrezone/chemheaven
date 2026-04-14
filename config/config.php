<?php
/**
 * ChemHeaven — Central Configuration
 * Copy this file to config/config.local.php and fill in real values.
 * config.local.php is git-ignored and MUST NOT be committed.
 */

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'chemheaven');
define('DB_USER', getenv('DB_USER') ?: 'chemheaven_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── OxoPay ───────────────────────────────────────────────────────────────────
// Set these via environment variables or config.local.php — never hard-code.
define('OXOPAY_MERCHANT_ID',  getenv('OXOPAY_MERCHANT_ID')  ?: '');
define('OXOPAY_API_KEY',      getenv('OXOPAY_API_KEY')      ?: '');
define('OXOPAY_CALLBACK_KEY', getenv('OXOPAY_CALLBACK_KEY') ?: '');
define('OXOPAY_SANDBOX',      filter_var(getenv('OXOPAY_SANDBOX') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('OXOPAY_CURRENCY',     'EUR');

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME',    'ChemHeaven');
define('APP_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));
define('APP_VERSION', '1.0.0');

// ── Session ───────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME',    3600);   // seconds
// __Host- prefix requires Secure + Path=/ + no Domain; only valid over HTTPS.
// Fall back to a plain name in non-HTTPS (local dev) environments.
define('SESSION_COOKIE_NAME',
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '__Host-chemheaven_sess' : 'chemheaven_sess'
);

// ── Privacy / Security ────────────────────────────────────────────────────────
// CSRF token length in bytes (hex-encoded → double length in string)
define('CSRF_TOKEN_BYTES', 32);

// ── Admin ─────────────────────────────────────────────────────────────────────
// Max failed login attempts before a temporary lockout.
define('ADMIN_MAX_ATTEMPTS',  5);
// Lockout duration in seconds (15 minutes).
define('ADMIN_LOCKOUT_SECS',  900);
// Admin session namespace key.
define('ADMIN_SESSION_KEY',   '_ch_admin');

// Load local overrides (not committed to git)
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require $localConfig;
}
