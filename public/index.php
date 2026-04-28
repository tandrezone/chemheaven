<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/index.php';
require_once __DIR__ . '/../src/admin.php';

use zRoute\Router;

$router = new Router();
$admin = new Admin();

$router->get('/', static function (array $params): void {
    main($params);
});

$router->get('/admin', static function (array $params) use ($admin): void {
    $admin->dashboard();
});

$router->post('/admin', static function (array $params) use ($admin): void {
    $admin->dashboard();
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
