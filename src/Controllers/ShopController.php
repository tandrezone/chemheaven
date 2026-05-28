<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Controllers;

use Tandrezone\Ztemp\TemplateEngine;
use Tandrezone\Chemheaven\Services\ImageManipulator;
use Tandrezone\OrderOrchestrator\OrderOrchestrator;
use CartOfficer\Cart;
use CartOfficer\CartController;
use Tandrezone\Chemheaven\Payment\PaymentManager;
use Tandrezone\OrderOrchestrator\OrderRepository;
use PDO;
use PDOException;

class ShopController
{
    private static ?PDO $pdo = null;

    /**
     * Build and cache a PDO connection for storefront catalog queries.
     */
    private static function db(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? ($_ENV['DB_DATABASE'] ?? 'product_management');
        $user = $_ENV['DB_USER'] ?? ($_ENV['DB_USERNAME'] ?? 'manager');
        $pass = $_ENV['DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? 'manager');
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    /**
     * Load all active products with category and variant rows from database.
     */
    private static function loadProductsFromDatabase(): array
    {
        $pdo = self::db();

        $productsStmt = $pdo->query(
            'SELECT p.id, p.name, p.slug, p.description, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = "active"
             ORDER BY p.created_at DESC, p.id DESC'
        );

        $products = [];
        $productIds = [];

        foreach ($productsStmt->fetchAll() as $row) {
            $id = (string) $row['id'];
            $products[$id] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'category' => (string) ($row['category_name'] ?? ''),
                'variants' => [],
            ];
            $productIds[] = (int) $row['id'];
        }

        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $variantsStmt = $pdo->prepare(
            "SELECT id, product_id, variant_name, price, stock_quantity
             FROM product_variants
             WHERE product_id IN ({$placeholders})
             ORDER BY id ASC"
        );
        $variantsStmt->execute($productIds);

        foreach ($variantsStmt->fetchAll() as $variantRow) {
            $productIdKey = (string) $variantRow['product_id'];
            if (!isset($products[$productIdKey])) {
                continue;
            }

            $products[$productIdKey]['variants'][] = [
                'id' => (string) $variantRow['id'],
                'label' => (string) ($variantRow['variant_name'] ?: 'Default'),
                'price' => (float) ($variantRow['price'] ?? 0),
                'stock' => (int) ($variantRow['stock_quantity'] ?? 0),
            ];
        }

        return array_values($products);
    }

    /**
     * Load one active product by slug including variants.
     */
    private static function loadProductBySlug(string $slug): ?array
    {
        $pdo = self::db();

        $productStmt = $pdo->prepare(
            'SELECT p.id, p.name, p.slug, p.description, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.slug = :slug AND p.status = "active"
             LIMIT 1'
        );
        $productStmt->execute(['slug' => $slug]);
        $row = $productStmt->fetch();

        if (!$row) {
            return null;
        }

        $product = [
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'category' => [
                'name' => (string) ($row['category_name'] ?? ''),
            ],
            'variants' => [],
        ];

        $variantsStmt = $pdo->prepare(
            'SELECT id, variant_name, price, stock_quantity
             FROM product_variants
             WHERE product_id = :product_id
             ORDER BY id ASC'
        );
        $variantsStmt->execute(['product_id' => (int) $row['id']]);

        foreach ($variantsStmt->fetchAll() as $variantRow) {
            $product['variants'][] = [
                'id' => (string) $variantRow['id'],
                'label' => (string) ($variantRow['variant_name'] ?: 'Default'),
                'price' => (float) ($variantRow['price'] ?? 0),
                'stock' => (int) ($variantRow['stock_quantity'] ?? 0),
            ];
        }

        return $product;
    }

    /**
     * Ensure session is started and return the CSRF token.
     */
    private static function ensureCsrf(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return csrf_token();
    }

    /**
     * Sanitize a user-supplied string for safe display.
     */
    private static function sanitize(string $value, int $maxLength = 255): string
    {
        $value = trim($value);
        $value = mb_substr($value, 0, $maxLength);
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Build a compact single-paragraph summary for product cards.
     */
    private static function buildCardDescription(string $description): string
    {
        $description = trim($description);
        if ($description === '') {
            return 'Analytical reference material for laboratory and characterization use.';
        }

        $parts = preg_split('/\R+/', $description) ?: [];
        $summaryLines = [];

        foreach ($parts as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, 'Formal Name:') || str_starts_with($line, 'CAS Number:') || str_starts_with($line, 'Molecular Formula:') || str_starts_with($line, 'Formula Weight:') || str_starts_with($line, 'Purity:') || str_starts_with($line, 'Formulation:') || str_starts_with($line, 'Reference Dose/Strength:')) {
                continue;
            }
            if (str_starts_with($line, 'Source Notes:')) {
                $line = trim(substr($line, strlen('Source Notes:')));
            }

            if ($line !== '') {
                $summaryLines[] = $line;
            }

            if (count($summaryLines) === 2) {
                break;
            }
        }

        $summary = trim(implode(' ', $summaryLines));
        if ($summary === '') {
            $summary = 'Analytical reference material for laboratory and characterization use.';
        }

        if (mb_strlen($summary) > 210) {
            $summary = rtrim(mb_substr($summary, 0, 207)) . '...';
        }

        return $summary;
    }

    public static function main(array $params = []): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $csrfToken = self::ensureCsrf();

        $engine = new TemplateEngine(__DIR__ . '/../../templates');
        try {
            $products = self::loadProductsFromDatabase();
        } catch (PDOException $exception) {
            $products = [];
        }

        foreach ($products as &$product) {
            $product['imagegen'] = ImageManipulator::createTextImageBase64(
                $product['name'], 
                __DIR__ . '/../ImageManipulator/assets/card_bg.png', 
                __DIR__ . '/../ImageManipulator/assets/Roboto-Regular.ttf'
            );
            $product['short_description'] = self::buildCardDescription((string) ($product['description'] ?? ''));
        }
        unset($product);

        $featuredCount = count($products);
        $variantCount = 0;
        foreach ($products as $product) {
            $variantCount += count($product['variants'] ?? []);
        }

        echo $engine->render('home.html', [
            'title' => 'ChemHeaven Store',
            'brand' => 'ChemHeaven',
            'tagline' => 'Curated compounds for a storefront.',
            'heading' => 'A storefront built around clear product cards.',
            'message' => 'Each product now lands in a styled card with artwork, concise descriptions, visible variants, and pricing that is easy to scan.',
            'featured_count' => str_pad((string) $featuredCount, 2, '0', STR_PAD_LEFT),
            'variant_count' => (string) $variantCount,
            'catalog_badge' => $featuredCount . ' products in catalog',
            'footer_text' => 'ChemHeaven product showcase powered by zRoute and ztemp.',
            'products' => $products,
            'csrf_token' => $csrfToken,
        ]);
    }

        public static function orderStatus(array $params = []): void
    {
        $orderid = $params['id'] ?? 'c27c28c0-5fef-4a12-aca4-65b34ec889bc';
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
$engine = new TemplateEngine(__DIR__ . '/../../templates');
        if(FILE_EXISTS(__DIR__ . '/../../api/orders/' . $orderid . '.json')) {
            $orderstatus = file_get_contents(__DIR__ . '/../../api/orders/' . $orderid . '.json');
        echo $engine->render('orderstatus.html', [
            'title' => 'ChemHeaven Store',
            'brand' => 'ChemHeaven',
            'tagline' => 'Curated compounds for a storefront.',
            'heading' => 'A storefront built around clear product cards.',
            'message' => 'Each product now lands in a styled card with artwork, concise descriptions, visible variants, and pricing that is easy to scan.',
            'orderstatus' => json_decode($orderstatus, true),
            'footer_text' => 'ChemHeaven product showcase powered by zRoute and ztemp.',
        ]);
        } else {
            echo $engine->render('error.html', [
                 'title' => 'ChemHeaven Store',
            'brand' => 'ChemHeaven',
            'footer_text' => 'ChemHeaven product showcase powered by zRoute and ztemp.',
                'error_subtitle' => 'Order Not Found',
                'error_description' => 'The order you are looking for does not exist.',
            ]);
        }

       

        
        

    }

    public static function product(array $params = []): void
    {
        $slug = self::sanitize($params['slug'] ?? '', 128);
        
        try {
            $foundProduct = self::loadProductBySlug($slug);
        } catch (PDOException $exception) {
            $foundProduct = null;
        }
        
        if (!$foundProduct) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Product Not Found</title></head><body><h1>Product not found.</h1><p><a href="/">Back to store</a></p></body></html>';
            return;
        }
        
        // Generate image artwork
        $foundProduct['imagegen'] = ImageManipulator::createTextImageBase64(
            $foundProduct['name'], 
            __DIR__ . '/../ImageManipulator/assets/card_bg.png', 
            __DIR__ . '/../ImageManipulator/assets/Roboto-Regular.ttf'
        );
        
        $csrfToken = self::ensureCsrf();

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        
        $engine = new TemplateEngine(__DIR__ . '/../../templates');
        echo $engine->render('product.html', [
            'title' => htmlspecialchars($foundProduct['name'], ENT_QUOTES, 'UTF-8') . ' - ChemHeaven Store',
            'product' => $foundProduct,
            'product_name' => htmlspecialchars($foundProduct['name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'product_description' => htmlspecialchars($foundProduct['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            'product_category_name' => htmlspecialchars($foundProduct['category']['name'] ?? (is_string($foundProduct['category'] ?? null) ? $foundProduct['category'] : ''), ENT_QUOTES, 'UTF-8'),
            'product_id' => htmlspecialchars($foundProduct['id'] ?? '', ENT_QUOTES, 'UTF-8'),
            'product_imagegen' => $foundProduct['imagegen'] ?? '',
            'csrf_token' => $csrfToken,
        ]);
    }

    public static function checkoutGet(array $params = []): void
    {
        $cart = new Cart();
        if ($cart->isEmpty()) {
            header('Location: /');
            exit;
        }
        
        $items = [];
        foreach ($cart->items() as $key => $item) {
            $items[] = array_merge($item->toArray(), [
                'key' => $key,
                'line_total' => number_format($item->lineTotal(), 2),
                'price_formatted' => number_format($item->price, 2)
            ]);
        }
        
        $subtotal = $cart->total();
        $shipping_cost = 10.00;
        
        $csrfToken = self::ensureCsrf();

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        
        $engine = new TemplateEngine(__DIR__ . '/../../templates');
        echo $engine->render('generic.html', [
            'debug' => 'This is a debug message for the checkout page.',
            'title' => 'Checkout - ChemHeaven Store',
            'items' => $items,
            'subtotal' => $subtotal,
            'subtotal_formatted' => number_format($subtotal, 2),
            'shipping_formatted' => number_format($shipping_cost, 2),
            'total_formatted' => number_format($subtotal + $shipping_cost, 2),
            'cart_payload_raw' => htmlspecialchars(json_encode([
                'items' => $cart->toArray(),
                'total' => $subtotal,
                'item_count' => $cart->count()
            ]), ENT_QUOTES, 'UTF-8'),
            'csrf_token' => $csrfToken,
        ]);
    }

    public static function checkoutPost(array $params = []): void
    {
        self::checkoutGet($params);
    }

    public static function paymentGet(array $params = []): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        
        $engine = new TemplateEngine(__DIR__ . '/../../templates');
        echo $engine->render('generic.html', [
            'debug' => 'This is a debug message for the payment page.',
            'title' => 'Checkout - ChemHeaven Store',
            'items' => $items,
            'subtotal' => $subtotal,
            'subtotal_formatted' => number_format($subtotal, 2),
            'shipping_formatted' => number_format($shipping_cost, 2),
            'total_formatted' => number_format($subtotal + $shipping_cost, 2),
            'cart_payload_raw' => htmlspecialchars(json_encode([
                'items' => $cart->toArray(),
                'total' => $subtotal,
                'item_count' => $cart->count()
            ]), ENT_QUOTES, 'UTF-8'),
            'csrf_token' => $csrfToken,
        ]);
    }

    public static function order(array $params = []): void
    {
        if (empty($params['products'])) {
            echo "No products selected.";
            return;
        }

        $orderOrchestrator = new OrderOrchestrator();

        echo $orderOrchestrator->renderOrderForm($params['products'], 'standard');

        $total = $orderOrchestrator->calculateTotal($params['products'], 'express');
    }

    public static function addToCart(array $params = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // ── CSRF validation for POST requests ────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validate()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid security token. Please refresh the page.']);
            return;
        }

        $cart       = new Cart();
        $controller = new CartController($cart, '/checkout', null);

        $controller->handle();
    }

    public static function privacy(array $params = []): void
    {
        $csrfToken = self::ensureCsrf();

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        
        $engine = new TemplateEngine(__DIR__ . '/../../templates');
        echo $engine->render('privacy.html', [
            'title' => 'Privacy Policy - ChemHeaven Store',
            'brand' => 'ChemHeaven',
            'tagline' => 'Curated compounds for a storefront.',
            'footer_text' => 'ChemHeaven product showcase powered by zRoute and ztemp.',
            'csrf_token' => $csrfToken,
        ]);
    }

    public static function paymentCallback(array $params = []): void
    {

    }

    public static function paymentReturn(array $params = []): void
    {
   
    }
}
