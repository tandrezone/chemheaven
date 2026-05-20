<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Controllers;

use Tandrezone\Ztemp\TemplateEngine;
use Tandrezone\Chemheaven\Services\ImageManipulator;
use Tandrezone\OrderOrchestrator\OrderOrchestrator;
use CartOfficer\Cart;
use CartOfficer\CartController;
use Tandrezone\Chemheaven\Payment\PaymentManager;

class ShopController
{
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

    public static function main(array $params = []): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $csrfToken = self::ensureCsrf();

        $engine = new TemplateEngine(__DIR__ . '/../../templates');
        $products = json_decode(file_get_contents(__DIR__ . '/../../api/products.json'), true)['products'] ?? [];
        foreach ($products as &$product) {
            $product['imagegen'] = ImageManipulator::createTextImageBase64(
                $product['name'], 
                __DIR__ . '/../ImageManipulator/assets/card_bg.png', 
                __DIR__ . '/../ImageManipulator/assets/Roboto-Regular.ttf'
            );
        }

        echo $engine->render('home.html', [
            'title' => 'ChemHeaven Store',
            'brand' => 'ChemHeaven',
            'tagline' => 'Curated compounds for a storefront.',
            'heading' => 'A storefront built around clear product cards.',
            'message' => 'Each product now lands in a styled card with artwork, concise descriptions, visible variants, and pricing that is easy to scan.',
            'featured_count' => '04',
            'variant_count' => '12',
            'catalog_badge' => '4 products in the launch edit',
            'footer_text' => 'ChemHeaven store mockup powered by zRoute and ztemp.',
            'products' => $products,
            'csrf_token' => $csrfToken,
        ]);
    }

    public static function product(array $params = []): void
    {
        $slug = self::sanitize($params['slug'] ?? '', 128);
        $products = json_decode(file_get_contents(__DIR__ . '/../../api/products.json'), true)['products'] ?? [];
        
        $foundProduct = null;
        foreach ($products as $p) {
            if (($p['slug'] ?? '') === $slug) {
                $foundProduct = $p;
                break;
            }
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
            __DIR__ . '/../../assets/card_bg.png', 
            __DIR__ . '/../../assets/Roboto-Regular.ttf'
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
        echo $engine->render('checkout.html', [
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
        // ── CSRF validation ──────────────────────────────────────────────
        if (!csrf_validate()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Invalid or missing security token. Please go back and try again.';
            return;
        }

        $cart = new Cart();
        if ($cart->isEmpty()) {
            header('Location: /');
            exit;
        }
        
        // ── Input sanitization & validation ──────────────────────────────
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            http_response_code(422);
            echo 'Invalid email address.';
            return;
        }

        $firstName = self::sanitize($_POST['first_name'] ?? '', 100);
        $lastName  = self::sanitize($_POST['last_name'] ?? '', 100);
        $address   = self::sanitize($_POST['address'] ?? '', 500);
        $zip       = self::sanitize($_POST['zip'] ?? '', 20);
        $city      = self::sanitize($_POST['city'] ?? '', 100);

        if (empty($firstName) || empty($lastName) || empty($address) || empty($zip) || empty($city)) {
            http_response_code(422);
            echo 'All required fields must be filled in.';
            return;
        }

        $shippingMethod = $_POST['shipping_method'] ?? 'standard';
        $shippingCost = match ($shippingMethod) {
            'express' => 25.50,
            'pickup' => 0.00,
            default => 10.00
        };
        
        $subtotal = $cart->total();
        $total = $subtotal + $shippingCost;
        
        $paymentManager = new PaymentManager();
        $driver = $paymentManager->getDriver('oxo');
        
        $orderId = 'CH-' . strtoupper(bin2hex(random_bytes(4)));
        $orderData = [
            'order_id' => $orderId,
            'total' => $total,
            'email' => $email,
            'name' => $firstName . ' ' . $lastName
        ];
        
        $invoice = $driver->createInvoice($orderData);
        
        // Store invoice in session for payment page access
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['active_invoice_' . $invoice['invoice_id']] = $invoice;
        $_SESSION['order_details_' . $invoice['invoice_id']] = [
            'order_id' => $orderId,
            'name' => $firstName . ' ' . $lastName,
            'address' => $address . ', ' . $city . ' ' . $zip,
            'shipping' => $shippingMethod,
            'shipping_cost' => $shippingCost,
            'subtotal' => $subtotal,
            'total' => $total,
            'email' => $email
        ];
        
        // Clear Cart since we have created an order / invoice successfully
        $cart->clear();
        
        // Redirect to the payment redirect URL
        header('Location: ' . $invoice['redirect_url']);
        exit;
    }

    public static function paymentGet(array $params = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $invoiceId = self::sanitize($_GET['invoice_id'] ?? '', 64);
        $paymentManager = new PaymentManager();
        $driver = $paymentManager->getDriver('oxo');
        
        if (($_GET['action'] ?? '') === 'status') {
            $status = $driver->checkPaymentStatus($invoiceId);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => $status]);
            exit;
        }
        
        if (($_GET['action'] ?? '') === 'pay') {
            $_SESSION["payment_status_{$invoiceId}"] = 'paid';
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'paid']);
            exit;
        }
        
        $invoice = $_SESSION['active_invoice_' . $invoiceId] ?? null;
        if (!$invoice) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Not Found</title></head><body><h1>Invoice not found.</h1><p><a href="/">Back to store</a></p></body></html>';
            return;
        }
        
        $csrfToken = self::ensureCsrf();

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        
        $engine = new TemplateEngine(__DIR__ . '/../../templates');
        echo $engine->render('payment.html', [
            'title' => 'Crypto Payment - ChemHeaven Store',
            'invoice_id' => htmlspecialchars($invoice['invoice_id'], ENT_QUOTES, 'UTF-8'),
            'amount_formatted' => number_format($invoice['amount'], 2),
            'address' => htmlspecialchars($invoice['address'], ENT_QUOTES, 'UTF-8'),
            'currency' => htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'),
            'qr_data_encoded' => urlencode($invoice['qr_data']),
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
        $controller = new CartController($cart, '/checkout', __DIR__ . '/../../api/products.json');

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
            'footer_text' => 'ChemHeaven store mockup powered by zRoute and ztemp.',
            'csrf_token' => $csrfToken,
        ]);
    }

    public static function paymentCallback(array $params = []): void
    {
        $rawBody = file_get_contents('php://input');
        
        // Helper to extract headers robustly
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        if (function_exists('getallheaders')) {
            $headers = array_merge($headers, getallheaders());
        }

        $hmacHeader = $headers['HMAC'] ?? $headers['Hmac'] ?? $headers['Http-Hmac'] ?? '';
        
        $paymentManager = new PaymentManager();
        $driver = $paymentManager->getDriver('oxo');
        
        if (!$driver->verifyWebhook($rawBody, $hmacHeader)) {
            http_response_code(403);
            echo "Invalid signature";
            exit;
        }
        
        $data = json_decode($rawBody, true);
        if (!$data) {
            http_response_code(400);
            echo "Invalid JSON body";
            exit;
        }
        
        $invoiceId = (string)($data['trackId'] ?? $data['track_id'] ?? '');
        $status = strtolower($data['status'] ?? '');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if ($status === 'paid' || $status === 'success') {
            $_SESSION["payment_status_{$invoiceId}"] = 'paid';
            // Also store completed details in session for return page receipt
            $_SESSION["completed_order_{$invoiceId}"] = [
                'invoice_id' => $invoiceId,
                'amount' => $data['amount'] ?? 0.0,
                'currency' => $data['currency'] ?? 'USDT',
                'payer_email' => $data['email'] ?? '',
                'date' => date('Y-m-d H:i:s'),
                'tx_id' => $data['txID'] ?? 'N/A'
            ];
        } elseif ($status === 'expired' || $status === 'failed') {
            $_SESSION["payment_status_{$invoiceId}"] = 'failed';
        }
        
        http_response_code(200);
        echo "ok";
        exit;
    }

    public static function paymentReturn(array $params = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $invoiceId = self::sanitize($_GET['invoice_id'] ?? $_GET['trackId'] ?? '', 64);
        
        $paymentManager = new PaymentManager();
        $driver = $paymentManager->getDriver('oxo');
        $status = $driver->checkPaymentStatus($invoiceId);
        
        // If live, and the status in inquiry is paid, we can force-complete it in session
        if ($status === 'paid' && !isset($_SESSION["completed_order_{$invoiceId}"])) {
            $_SESSION["payment_status_{$invoiceId}"] = 'paid';
        }

        $orderDetails = $_SESSION['order_details_' . $invoiceId] ?? null;
        $completedDetails = $_SESSION['completed_order_' . $invoiceId] ?? null;
        
        $csrfToken = self::ensureCsrf();
        
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        
        $engine = new TemplateEngine(__DIR__ . '/../../templates');
        echo $engine->render('payment-return.html', [
            'title' => 'Order Confirmation - ChemHeaven Store',
            'invoice_id' => htmlspecialchars($invoiceId, ENT_QUOTES, 'UTF-8'),
            'status' => $status,
            'order' => $orderDetails,
            'completed' => $completedDetails,
            'csrf_token' => $csrfToken,
        ]);
    }
}
