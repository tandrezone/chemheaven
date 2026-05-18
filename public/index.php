<?php
declare(strict_types=1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/index.php';


use zRoute\Router;

$router = new Router();


$router->get('/', static function (array $params): void {
    main($params);
});

$router->post('/order', static function (array $params): void {
    order($params);
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
