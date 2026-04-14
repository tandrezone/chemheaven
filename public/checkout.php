<?php
/**
 * ChemHeaven — Checkout Page
 *
 * Privacy-first: we collect only the minimum information needed to process
 * a payment. No account required. Cart stays in session; order row is
 * created only once payment is initiated.
 *
 * Payment gateway: OxoPay (https://oxopay.com)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

send_security_headers();
session_secure_start();

$cart  = cart_get();
$total = cart_total();

// Redirect to cart if it's empty
if (empty($cart)) {
    safe_redirect('/cart.php');
}

$errors = [];

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Validate email (optional — customer may leave blank for anonymous order)
    $email = trim(strip_tags($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address or leave the field blank.';
    }

    // Limit email length
    if (mb_strlen($email) > 254) {
        $errors[] = 'Email address is too long.';
    }

    $name = mb_substr(trim(strip_tags($_POST['name'] ?? '')), 0, 200);

    if (empty($errors)) {
        // ── Create order record ───────────────────────────────────────────────
        $orderRef = bin2hex(random_bytes(16)); // unique, unguessable ref

        $db = db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO orders (order_ref, customer_email, customer_name, total_amount, currency, status)
                 VALUES (:ref, :email, :name, :total, :currency, "pending")'
            );
            $stmt->execute([
                ':ref'      => $orderRef,
                ':email'    => $email !== '' ? $email : null,
                ':name'     => $name !== '' ? $name : null,
                ':total'    => $total,
                ':currency' => OXOPAY_CURRENCY,
            ]);
            $orderId = (int) $db->lastInsertId();

            // Insert order items
            $itemStmt = $db->prepare(
                'INSERT INTO order_items
                    (order_id, product_id, variant_id, product_name, weight_label, price, quantity)
                 VALUES
                    (:oid, :pid, :vid, :pname, :wlabel, :price, :qty)'
            );

            foreach ($cart as $item) {
                $itemStmt->execute([
                    ':oid'    => $orderId,
                    ':pid'    => $item['product_id'],
                    ':vid'    => $item['variant_id'],
                    ':pname'  => $item['product_name'],
                    ':wlabel' => $item['weight_label'],
                    ':price'  => $item['price'],
                    ':qty'    => $item['quantity'],
                ]);
            }

            $db->commit();

        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[ChemHeaven] Order creation failed: ' . $e->getMessage());
            $errors[] = 'An error occurred. Please try again.';
        }

        if (empty($errors)) {
            // ── Initiate OxoPay payment ───────────────────────────────────────
            $paymentUrl = oxopay_create_payment($orderRef, $total, $email);

            if ($paymentUrl) {
                // Store order ref in session to validate callback
                $_SESSION['pending_order_ref'] = $orderRef;
                // Redirect to payment provider
                header('Location: ' . $paymentUrl, true, 303);
                exit;
            } else {
                // Mark order as failed
                db()->prepare('UPDATE orders SET status = "failed" WHERE order_ref = :ref')
                     ->execute([':ref' => $orderRef]);
                $errors[] = 'Could not connect to the payment gateway. Please try again.';
            }
        }
    }
}

// ── OxoPay helper ─────────────────────────────────────────────────────────────
/**
 * Create a payment via the OxoPay API and return the redirect URL,
 * or null on failure.
 *
 * Docs: https://oxopay.com/documentation
 */
function oxopay_create_payment(string $orderRef, float $amount, string $email): ?string
{
    if (OXOPAY_MERCHANT_ID === '' || OXOPAY_API_KEY === '') {
        error_log('[ChemHeaven] OxoPay credentials not configured.');
        return null;
    }

    $callbackUrl = APP_URL . '/payment-callback.php';
    $returnUrl   = APP_URL . '/payment-return.php?ref=' . urlencode($orderRef);

    $payload = json_encode([
        'merchant'     => OXOPAY_MERCHANT_ID,
        'amount'       => number_format($amount, 2, '.', ''),
        'currency'     => OXOPAY_CURRENCY,
        'order_id'     => $orderRef,
        'email'        => $email,
        'callback_url' => $callbackUrl,
        'return_url'   => $returnUrl,
        'description'  => 'ChemHeaven order ' . $orderRef,
    ], JSON_THROW_ON_ERROR);

    $apiBase = OXOPAY_SANDBOX
        ? 'https://sandbox.oxopay.com/api/v1'
        : 'https://api.oxopay.com/api/v1';

    $ch = curl_init($apiBase . '/payment');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OXOPAY_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,   // never disable TLS verification
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[ChemHeaven] OxoPay curl error: ' . $curlErr);
        return null;
    }

    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log('[ChemHeaven] OxoPay HTTP ' . $httpCode . ': ' . $response);
        return null;
    }

    $data = json_decode($response, true);
    return $data['payment_url'] ?? $data['url'] ?? null;
}

// ── Render ────────────────────────────────────────────────────────────────────
$pageTitle = 'Checkout';
$bodyClass = 'page-checkout';
require __DIR__ . '/../includes/header.php';
?>

<div class="checkout-page">
    <h1>Checkout</h1>

    <?php if (!empty($errors)): ?>
        <ul class="error-list" role="alert">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- Order summary -->
    <section class="order-summary">
        <h2>Order summary</h2>
        <table class="cart-table">
            <thead>
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col">Weight</th>
                    <th scope="col">Qty</th>
                    <th scope="col">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart as $item): ?>
                <tr>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= h($item['weight_label']) ?></td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td><?= format_price($item['price'] * $item['quantity']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" scope="row">Total</th>
                    <td class="cart-total"><?= format_price($total) ?></td>
                </tr>
            </tfoot>
        </table>
    </section>

    <!-- Contact (optional) -->
    <section class="checkout-form-section">
        <h2>Contact <small>(optional)</small></h2>
        <p class="privacy-note">
            ℹ️ Your details are used only to send an order confirmation.
            We do not share your data with anyone.
        </p>

        <form method="post" action="/checkout.php" class="checkout-form" novalidate>
            <?= csrf_field() ?>

            <label for="checkout-name">Name</label>
            <input
                type="text"
                id="checkout-name"
                name="name"
                placeholder="Optional"
                maxlength="200"
                autocomplete="name"
                value="<?= h($_POST['name'] ?? '') ?>"
            >

            <label for="checkout-email">Email</label>
            <input
                type="email"
                id="checkout-email"
                name="email"
                placeholder="Optional — for order confirmation only"
                maxlength="254"
                autocomplete="email"
                value="<?= h($_POST['email'] ?? '') ?>"
            >

            <button type="submit" class="btn-primary btn-pay">
                Pay <?= format_price($total) ?> with OxoPay
            </button>
        </form>
    </section>
</div><!-- /.checkout-page -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
