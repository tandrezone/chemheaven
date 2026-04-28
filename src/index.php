<?php

declare(strict_types=1);

use Tandrezone\Ztemp\TemplateEngine;

function main(array $params = []): void
{
    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');

    $engine = new TemplateEngine(__DIR__ . '/../templates');
    $products = [
        [
            'image' => '/assets/store/amber-drops.svg',
            'category' => 'Signature oils',
            'name' => 'Amber Drops',
            'description' => 'Citrus-led house blend with a smooth finish and an easy everyday profile.',
            'variants' => 'Variants: 10 ml, 30 ml, 50 ml',
            'price' => 'From EUR 18.90',
        ],
        [
            'image' => '/assets/store/citrus-lab.svg',
            'category' => 'Lab edition',
            'name' => 'Citrus Lab',
            'description' => 'Bright, clean notes built for customers who want a sharp and fresh opening.',
            'variants' => 'Variants: 1 pack, 3 pack, 6 pack',
            'price' => 'From EUR 12.50',
        ],
        [
            'image' => '/assets/store/nocturne-mix.svg',
            'category' => 'Evening release',
            'name' => 'Nocturne Mix',
            'description' => 'A deeper profile with layered botanicals and a fuller body for premium shelves.',
            'variants' => 'Variants: 15 g, 30 g, 60 g',
            'price' => 'From EUR 24.00',
        ],
        [
            'image' => '/assets/store/verdant-wave.svg',
            'category' => 'Botanical line',
            'name' => 'Verdant Wave',
            'description' => 'Green, bright, and balanced, packaged as a clean modern staple for the store.',
            'variants' => 'Variants: Starter, Core, Collector',
            'price' => 'From EUR 16.40',
        ],
    ];

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
