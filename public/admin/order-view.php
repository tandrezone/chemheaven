<?php
/**
 * ChemHeaven — Admin: Order Detail + Status Update
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

$db = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { safe_redirect('/admin/orders.php'); }

$order = $db->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
$order->execute([':id' => $id]);
$order = $order->fetch();
if (!$order) { safe_redirect('/admin/orders.php'); }

$items = $db->prepare(
    'SELECT oi.*, p.slug AS product_slug
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = :oid'
);
$items->execute([':oid' => $id]);
$items = $items->fetchAll();

$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['pending','paid','failed','cancelled'], true)) {
        $db->prepare('UPDATE orders SET status=:s WHERE id=:id')->execute([':s'=>$newStatus,':id'=>$id]);
        $_SESSION['_flash'] = ['type'=>'success','msg'=>'Order status updated.'];
        safe_redirect('/admin/order-view.php?id='.$id);
    }
}

$adminPageTitle = 'Order #' . $order['id'];
$adminActiveNav = 'orders';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header">
    <h1>Order #<?= $order['id'] ?></h1>
    <a href="/admin/orders.php" class="btn-secondary">← Back</a>
</div>

<?php if ($flash): ?>
    <div class="admin-alert admin-alert--<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="admin-form-grid">
    <div class="admin-form-main">
        <div class="admin-card">
            <h2>Items</h2>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Weight</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['product_slug']): ?>
                                    <a href="/product.php?slug=<?= urlencode($item['product_slug']) ?>" target="_blank"><?= h($item['product_name']) ?></a>
                                <?php else: ?>
                                    <?= h($item['product_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= h($item['weight_label']) ?></td>
                            <td><?= format_price((float)$item['price']) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td><?= format_price($item['price'] * $item['quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="4" style="text-align:right">Total</th><td><?= format_price((float)$order['total_amount']) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="admin-form-sidebar">
        <div class="admin-card">
            <h2>Order info</h2>
            <dl class="admin-dl">
                <dt>Ref</dt><dd><code><?= h($order['order_ref']) ?></code></dd>
                <dt>Customer</dt><dd><?= h($order['customer_name'] ?: '—') ?></dd>
                <dt>Email</dt><dd><?= h($order['customer_email'] ?: '—') ?></dd>
                <dt>Created</dt><dd><?= h(date('d M Y H:i', strtotime($order['created_at']))) ?></dd>
                <dt>Payment ID</dt><dd><?= h($order['payment_id'] ?: '—') ?></dd>
                <dt>Status</dt><dd><span class="status-badge status-<?= h($order['status']) ?>"><?= h($order['status']) ?></span></dd>
            </dl>
        </div>

        <div class="admin-card">
            <h2>Update status</h2>
            <form method="post" action="/admin/order-view.php?id=<?= $order['id'] ?>" class="admin-form">
                <?= csrf_field() ?>
                <label>Status
                    <select name="status">
                        <?php foreach (['pending','paid','failed','cancelled'] as $s): ?>
                            <option value="<?= $s ?>"<?= $order['status'] === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn-primary">Update</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
