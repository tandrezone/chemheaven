<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use zRoute\Router;
use Tandrezone\Chemheaven\Controllers\ShopController;

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

$router->post('/order', static function (array $params): void {
    ShopController::order($params);
});

$router->post('/cart', static function (array $params): void {
    ShopController::addToCart($params);
});
$router->get('/cart', static function (array $params): void {
    ShopController::addToCart($params);
});

$router->notFound(static function (string $path): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');

    echo '404 Not Found';
});

$router->methodNotAllowed(static function (string $method, string $path): void {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');

    echo '405 Method Not Allowed';
});

$router->run();
