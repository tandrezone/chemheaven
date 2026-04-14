<?php
/**
 * ChemHeaven — Admin Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

$db = db();

// Stats
$stats = [
    'products' => (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'orders'   => (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'revenue'  => (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='paid'")->fetchColumn(),
    'pending'  => (int)$db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
];

// Recent orders
$recentOrders = $db->query(
    "SELECT id, order_ref, customer_name, customer_email, total_amount, status, created_at
     FROM orders ORDER BY created_at DESC LIMIT 10"
)->fetchAll();

$adminPageTitle = 'Dashboard';
$adminActiveNav = 'dashboard';

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header">
    <h1>Dashboard</h1>
</div>

<!-- Stats -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $stats['products'] ?></div>
        <div class="stat-label">Products</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['orders'] ?></div>
        <div class="stat-label">Total orders</div>
    </div>
    <div class="stat-card stat-card--success">
        <div class="stat-value"><?= format_price($stats['revenue']) ?></div>
        <div class="stat-label">Revenue (paid)</div>
    </div>
    <div class="stat-card stat-card--warning">
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-label">Pending orders</div>
    </div>
</div>

<!-- Recent orders -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2>Recent orders</h2>
        <a href="/admin/orders.php" class="btn-secondary btn-sm">View all</a>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><code><?= h(substr($o['order_ref'], 0, 12)) ?>…</code></td>
                    <td><?= h($o['customer_name'] ?: $o['customer_email'] ?: '—') ?></td>
                    <td><?= format_price((float)$o['total_amount']) ?></td>
                    <td><span class="status-badge status-<?= h($o['status']) ?>"><?= h($o['status']) ?></span></td>
                    <td><?= h(date('d M Y', strtotime($o['created_at']))) ?></td>
                    <td><a href="/admin/order-view.php?id=<?= $o['id'] ?>" class="btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentOrders)): ?>
                <tr><td colspan="6" class="text-muted text-center">No orders yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
