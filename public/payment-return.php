<?php
/**
 * ChemHeaven — Payment Return Page
 * The customer lands here after completing (or cancelling) payment on OxoPay.
 * We only show a status message — never assume success based on URL alone;
 * the canonical status is set by the server-side callback.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

send_security_headers();
session_secure_start();

// Validate the order ref from the URL
$rawRef = $_GET['ref'] ?? '';
$orderRef = preg_match('/^[0-9a-f]{32}$/', $rawRef) ? $rawRef : '';

$order = null;
if ($orderRef !== '') {
    $stmt = db()->prepare(
        'SELECT order_ref, status, total_amount, currency
         FROM orders
         WHERE order_ref = :ref
         LIMIT 1'
    );
    $stmt->execute([':ref' => $orderRef]);
    $order = $stmt->fetch();
}

// Clear cart on confirmed payment
if ($order && $order['status'] === 'paid') {
    // Only clear if this session initiated the order
    if (($_SESSION['pending_order_ref'] ?? '') === $orderRef) {
        cart_clear();
        unset($_SESSION['pending_order_ref']);
    }
}

$pageTitle = 'Payment status';
$bodyClass = 'page-payment-return';
require __DIR__ . '/../includes/header.php';
?>

<div class="payment-return-page">
    <?php if (!$order): ?>
        <div class="status-box status-unknown">
            <h1>Order not found</h1>
            <p>We could not find an order matching this reference. <a href="/">Return to shop</a>.</p>
        </div>

    <?php elseif ($order['status'] === 'paid'): ?>
        <div class="status-box status-success">
            <h1>✅ Payment confirmed!</h1>
            <p>Thank you for your order. Your payment of
               <strong><?= format_price((float)$order['total_amount']) ?></strong>
               has been received.</p>
            <p>Order reference: <code><?= h($order['order_ref']) ?></code></p>
            <a href="/" class="btn-primary">Back to shop</a>
        </div>

    <?php elseif ($order['status'] === 'pending'): ?>
        <div class="status-box status-pending">
            <h1>⏳ Payment pending</h1>
            <p>We are waiting for confirmation from the payment provider.
               This page will update automatically.</p>
            <p>Order reference: <code><?= h($order['order_ref']) ?></code></p>
            <!-- Minimal JS: single auto-refresh after 5 s, then stops -->
            <script>
            (function() {
                var refreshed = sessionStorage.getItem('ch_refreshed_' + <?= json_encode($orderRef) ?>);
                if (!refreshed) {
                    sessionStorage.setItem('ch_refreshed_' + <?= json_encode($orderRef) ?>, '1');
                    setTimeout(function() { window.location.reload(); }, 5000);
                }
            }());
            </script>
        </div>

    <?php else: ?>
        <div class="status-box status-failed">
            <h1>❌ Payment failed</h1>
            <p>Your payment could not be processed. Your cart has been preserved.</p>
            <a href="/cart.php" class="btn-primary">Return to cart</a>
        </div>

    <?php endif; ?>
</div><!-- /.payment-return-page -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
