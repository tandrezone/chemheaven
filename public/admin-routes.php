<?php

declare(strict_types=1);

use zRoute\Router;
use Tandrezone\Chemheaven\Controllers\AdminController;
use Tandrezone\Chemheaven\Controllers\AdminCategoryController;
use Tandrezone\Chemheaven\Controllers\AdminProductController;
use Tandrezone\Chemheaven\Controllers\AdminPaymentGatewayController;
use Tandrezone\Chemheaven\Controllers\AdminShippingController;

/**
 * Wrap a controller callback so arrow-function implicit returns do not violate void.
 */
function admin_handler(callable $handler): callable
{
    return static function (array $params) use ($handler) {
        $handler($params);
    };
}

/**
 * Register admin panel routes for a given URL prefix (/admin or /administration).
 */
function register_admin_routes(Router $router, string $base): void
{
    $router->get("{$base}/login", admin_handler([AdminController::class, 'loginGet']));
    $router->post("{$base}/login", admin_handler([AdminController::class, 'loginPost']));
    $router->post("{$base}/logout", admin_handler([AdminController::class, 'logoutPost']));

    $router->get($base, admin_handler([AdminController::class, 'dashboard']));

    $router->get("{$base}/categories", admin_handler([AdminCategoryController::class, 'index']));
    $router->get("{$base}/categories/new", admin_handler([AdminCategoryController::class, 'createForm']));
    $router->get($base . '/categories/$id/edit', admin_handler([AdminCategoryController::class, 'editForm']));
    $router->post("{$base}/categories", admin_handler([AdminCategoryController::class, 'store']));
    $router->post($base . '/categories/$id', admin_handler([AdminCategoryController::class, 'update']));
    $router->post($base . '/categories/$id/delete', admin_handler([AdminCategoryController::class, 'delete']));

    $router->get("{$base}/products", admin_handler([AdminProductController::class, 'index']));
    $router->get("{$base}/products/new", admin_handler([AdminProductController::class, 'createForm']));
    $router->get($base . '/products/$id/edit', admin_handler([AdminProductController::class, 'editForm']));
    $router->post("{$base}/products", admin_handler([AdminProductController::class, 'store']));
    $router->post($base . '/products/$id', admin_handler([AdminProductController::class, 'update']));
    $router->post($base . '/products/$id/delete', admin_handler([AdminProductController::class, 'delete']));

    $router->get("{$base}/payment-gateways", admin_handler([AdminPaymentGatewayController::class, 'index']));
    $router->get("{$base}/payment-gateways/new", admin_handler([AdminPaymentGatewayController::class, 'createForm']));
    $router->get($base . '/payment-gateways/$id/edit', admin_handler([AdminPaymentGatewayController::class, 'editForm']));
    $router->post("{$base}/payment-gateways", admin_handler([AdminPaymentGatewayController::class, 'store']));
    $router->post($base . '/payment-gateways/$id', admin_handler([AdminPaymentGatewayController::class, 'update']));
    $router->post($base . '/payment-gateways/$id/delete', admin_handler([AdminPaymentGatewayController::class, 'delete']));

    $router->get("{$base}/shipping", admin_handler([AdminShippingController::class, 'index']));
    $router->get("{$base}/shipping/new", admin_handler([AdminShippingController::class, 'createForm']));
    $router->get($base . '/shipping/$id/edit', admin_handler([AdminShippingController::class, 'editForm']));
    $router->post("{$base}/shipping", admin_handler([AdminShippingController::class, 'store']));
    $router->post($base . '/shipping/$id', admin_handler([AdminShippingController::class, 'update']));
    $router->post($base . '/shipping/$id/delete', admin_handler([AdminShippingController::class, 'delete']));
}
