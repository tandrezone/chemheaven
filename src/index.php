<?php

declare(strict_types=1);

use Tandrezone\Ztemp\TemplateEngine;
use Tandrezone\Services\ImageManipulator;
use Tandrezone\OrderOrchestrator\OrderOrchestrator;

function main(array $params = []): void
{
    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');

    $engine = new TemplateEngine(__DIR__ . '/../templates');
    $products = json_decode(file_get_contents(__DIR__ . '/../api/products.json'), true)['products'] ?? [];
    foreach ($products as &$product) {
       $product['imagegen'] = ImageManipulator::createTextImageBase64($product['name'], __DIR__ . '/../assets/card_bg.png', __DIR__ . '/../assets/Roboto-Regular.ttf');
    }
 //   echo "<pre>";
//print_r($products);
//echo "</pre>";
//exit();
    echo $engine->render('home.html', [
        'title' => 'ChemHeaven Store',
        'brand' => 'ChemHeaven',
        'tagline' => 'Curated compounds for a sharper storefront.',
        'heading' => 'A storefront built around clear product cards.',
        'message' => 'Each product now lands in a styled card with artwork, concise descriptions, visible variants, and pricing that is easy to scan.',
        'featured_count' => '04',
        'variant_count' => '12',
        'catalog_badge' => '4 products in the launch edit',
        'footer_text' => 'ChemHeaven store mockup powered by zRoute and ztemp.',
        'products' => $products,
    ]);
}

function order(array $params = []): void
{
    if(empty($params['products'])) {
        echo "No products selected.";
        return;
    }

    $orderOrchestrator = new OrderOrchestrator();

    echo $orderOrchestrator->renderOrderForm($params['products'], 'standard');

    $total = $orderOrchestrator->calculateTotal($params['products'], 'express');
}
