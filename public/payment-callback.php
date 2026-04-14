<?php
/**
 * ChemHeaven — OxoPay Payment Callback (Webhook)
 *
 * This endpoint is called by OxoPay server-to-server.
 * It MUST NOT output anything other than a plain-text acknowledgement.
 *
 * Security:
 *  - Verifies HMAC-SHA256 signature using OXOPAY_CALLBACK_KEY
 *  - Only accepts POST
 *  - Idempotent: safe to call multiple times for the same order
 *  - Never exposes internal error details to the caller
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Only log errors server-side; respond with minimal information.
function callback_fail(string $reason, int $code = 400): never
{
    error_log('[ChemHeaven] Payment callback rejected: ' . $reason);
    http_response_code($code);
    exit('Error');
}

// ── Method guard ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    callback_fail('Not POST', 405);
}

// ── Read raw body ─────────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    callback_fail('Empty body');
}

// ── Signature verification ────────────────────────────────────────────────────
// OxoPay sends the signature in the X-Oxopay-Signature header.
$signature = $_SERVER['HTTP_X_OXOPAY_SIGNATURE'] ?? '';

if (OXOPAY_CALLBACK_KEY === '') {
    error_log('[ChemHeaven] OXOPAY_CALLBACK_KEY is not configured — skipping signature check.');
} else {
    $expected = hash_hmac('sha256', $rawBody, OXOPAY_CALLBACK_KEY);
    // Use hash_equals to prevent timing attacks.
    if (!hash_equals($expected, $signature)) {
        callback_fail('Invalid signature');
    }
}

// ── Parse payload ─────────────────────────────────────────────────────────────
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    callback_fail('Invalid JSON');
}

$orderRef  = $data['order_id']   ?? '';
$status    = $data['status']     ?? '';   // e.g. "paid", "failed", "cancelled"
$paymentId = $data['payment_id'] ?? '';

// Validate order_ref format (must match what we generate: 32 hex chars)
if (!preg_match('/^[0-9a-f]{32}$/', $orderRef)) {
    callback_fail('Invalid order reference');
}

// Map OxoPay status to our status enum
$allowedStatuses = ['paid', 'failed', 'cancelled'];
$newStatus = in_array($status, $allowedStatuses, true) ? $status : null;

if ($newStatus === null) {
    // Unknown status — acknowledge but don't change order state.
    error_log('[ChemHeaven] Unknown OxoPay status: ' . $status);
    http_response_code(200);
    exit('OK');
}

// ── Update order ──────────────────────────────────────────────────────────────
try {
    $stmt = db()->prepare(
        'UPDATE orders
         SET status = :status, payment_id = :pid
         WHERE order_ref = :ref
           AND status = "pending"'  // Only update if still pending (idempotency)
    );
    $stmt->execute([
        ':status' => $newStatus,
        ':pid'    => mb_substr((string)$paymentId, 0, 255),
        ':ref'    => $orderRef,
    ]);
} catch (\PDOException $e) {
    error_log('[ChemHeaven] DB error in callback: ' . $e->getMessage());
    http_response_code(500);
    exit('Error');
}

http_response_code(200);
exit('OK');
