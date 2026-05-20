<?php
declare(strict_types=1);

// ── Security: suppress error display to visitors ──────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

// ── Load Environment Variables ───────────────────────────────────────────
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

use zRoute\Router;
use Tandrezone\Chemheaven\Controllers\ShopController;

// ── Security: secure session configuration ────────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

// ── Security: HTTP security headers ───────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://chart.googleapis.com; connect-src 'self';");

// ── CSRF token helper ─────────────────────────────────────────────────────
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_validate(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check header first (AJAX), then POST body (forms)
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
           ?? $_POST['_csrf_token']
           ?? '';

    return !empty($token)
        && isset($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

$router = new Router();

$router->get('/', static function (array $params): void {
    ShopController::main($params);
});

$router->get('/product/$slug', static function (array $params): void {
    ShopController::product($params);
});

$router->get('/checkout', static function (array $params): void {
    ShopController::checkoutGet($params);
});

$router->post('/checkout', static function (array $params): void {
    ShopController::checkoutPost($params);
});

$router->get('/checkout/payment', static function (array $params): void {
    ShopController::paymentGet($params);
});

$router->post('/checkout/payment-callback', static function (array $params): void {
    ShopController::paymentCallback($params);
});

$router->get('/checkout/payment-return', static function (array $params): void {
    ShopController::paymentReturn($params);
});

$router->post('/order', static function (array $params): void {
    ShopController::order($params);
});

$router->post('/cart', static function (array $params): void {
    ShopController::addToCart($params);
});
$router->get('/cart', static function (array $params): void {
    ShopController::addToCart($params);
});

$router->get('/privacy', static function (array $params): void {
    ShopController::privacy($params);
});

$router->notFound(static function (string $path): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>';
});

$router->methodNotAllowed(static function (string $method, string $path): void {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '405 Method Not Allowed';
});

$router->run();
