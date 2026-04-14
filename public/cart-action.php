<?php
/**
 * ChemHeaven — Cart Action Handler
 *
 * Accepts POST requests only. Performs add / remove / update actions.
 * Validates CSRF token, validates all inputs, then redirects back.
 *
 * No sensitive data is stored or logged.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

send_security_headers();
session_secure_start();

// ── Method guard ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
csrf_verify();

// ── Input ─────────────────────────────────────────────────────────────────────
$action    = $_POST['action']     ?? '';
$variantId = filter_input(INPUT_POST, 'variant_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 999],
]) ?? 1;

$redirect = '/cart.php';

switch ($action) {
    case 'add':
        if ($variantId !== false && $variantId !== null) {
            cart_add($variantId, max(1, (int)$qty));
        }
        // After adding from the shop page, go back to shop
        $ref = $_POST['_ref'] ?? '';
        if ($ref === 'shop') {
            $redirect = '/';
        }
        break;

    case 'remove':
        if ($variantId !== false && $variantId !== null) {
            cart_remove($variantId);
        }
        break;

    case 'update':
        if ($variantId !== false && $variantId !== null) {
            cart_update($variantId, (int)$qty);
        }
        break;

    case 'clear':
        cart_clear();
        break;
}

safe_redirect($redirect);
