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
// These are applied before session_start() in header.php.
define('SESSION_LIFETIME',    3600);   // seconds
define('SESSION_COOKIE_NAME', '__Host-chemheaven_sess');

// ── Privacy / Security ────────────────────────────────────────────────────────
// CSRF token length in bytes (hex-encoded → double length in string)
define('CSRF_TOKEN_BYTES', 32);

// Load local overrides (not committed to git)
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require $localConfig;
}
