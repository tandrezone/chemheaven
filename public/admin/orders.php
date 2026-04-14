<?php
/**
 * ChemHeaven — Admin: Order List
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

$db    = db();
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

$statusFilter = in_array($_GET['status'] ?? '', ['pending','paid','failed','cancelled'], true)
    ? $_GET['status'] : '';

$where  = $statusFilter ? 'WHERE status = :s' : '';
$params = $statusFilter ? [':s' => $statusFilter] : [];

$orders = $db->prepare(
    "SELECT id, order_ref, customer_name, customer_email, total_amount, currency, status, created_at
     FROM orders $where ORDER BY created_at DESC"
);
$orders->execute($params);
$orders = $orders->fetchAll();

$adminPageTitle = 'Orders';
$adminActiveNav = 'orders';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header"><h1>Orders</h1></div>

<?php if ($flash): ?>
    <div class="admin-alert admin-alert--<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="admin-filter-bar">
    <?php foreach ([''=>'All','pending'=>'Pending','paid'=>'Paid','failed'=>'Failed','cancelled'=>'Cancelled'] as $s => $label): ?>
        <a href="/admin/orders.php<?= $s ? '?status='.$s : '' ?>" class="category-btn<?= $statusFilter === $s ? ' active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<div class="admin-card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><code><?= h(substr($o['order_ref'],0,16)) ?>…</code></td>
                    <td><?= h($o['customer_name'] ?: $o['customer_email'] ?: 'Anonymous') ?></td>
                    <td><?= format_price((float)$o['total_amount'], $o['currency'] === 'EUR' ? '€' : $o['currency']) ?></td>
                    <td><span class="status-badge status-<?= h($o['status']) ?>"><?= h($o['status']) ?></span></td>
                    <td><?= h(date('d M Y H:i', strtotime($o['created_at']))) ?></td>
                    <td><a href="/admin/order-view.php?id=<?= $o['id'] ?>" class="btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="6" class="text-muted text-center">No orders.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
