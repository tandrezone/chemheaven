<?php
/**
 * ChemHeaven — Cart Page
 * Displays cart contents, quantity controls, and a link to checkout.
 * Pure PHP sessions — no cart data persisted to the database.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Your Cart';
$bodyClass = 'page-cart';
require __DIR__ . '/../includes/header.php';

$cart  = cart_get();
$total = cart_total();
?>

<div class="cart-page">
    <h1>Your Cart</h1>

    <?php if (empty($cart)): ?>
        <p class="cart-empty">Your cart is empty. <a href="/">Continue shopping</a>.</p>
    <?php else: ?>

    <div class="cart-table-wrap">
        <table class="cart-table">
            <thead>
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col">Weight</th>
                    <th scope="col">Unit price</th>
                    <th scope="col">Qty</th>
                    <th scope="col">Subtotal</th>
                    <th scope="col"><span class="sr-only">Remove</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cart as $item): ?>
                <tr>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= h($item['weight_label']) ?></td>
                    <td><?= format_price($item['price']) ?></td>
                    <td>
                        <!-- Quantity update form -->
                        <form method="post" action="/cart-action.php" class="qty-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"     value="update">
                            <input type="hidden" name="variant_id" value="<?= (int)$item['variant_id'] ?>">
                            <input
                                type="number"
                                name="quantity"
                                value="<?= (int)$item['quantity'] ?>"
                                min="0"
                                max="999"
                                class="qty-input"
                                aria-label="Quantity for <?= h($item['product_name']) ?>"
                            >
                            <button type="submit" class="btn-qty">Update</button>
                        </form>
                    </td>
                    <td><?= format_price($item['price'] * $item['quantity']) ?></td>
                    <td>
                        <!-- Remove form -->
                        <form method="post" action="/cart-action.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"     value="remove">
                            <input type="hidden" name="variant_id" value="<?= (int)$item['variant_id'] ?>">
                            <button type="submit" class="btn-remove" aria-label="Remove <?= h($item['product_name']) ?>">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" scope="row">Total</th>
                    <td colspan="2" class="cart-total"><?= format_price($total) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="cart-actions">
        <form method="post" action="/cart-action.php" class="clear-cart-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn-secondary">Clear cart</button>
        </form>

        <a href="/checkout.php" class="btn-primary">Proceed to checkout</a>
    </div>

    <?php endif; ?>
</div><!-- /.cart-page -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
