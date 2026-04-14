<?php
/**
 * ChemHeaven — Admin HTML Header
 * Outputs the admin <head>, sidebar nav, and opens the main content area.
 * $adminPageTitle and $adminActiveNav must be set by the including page.
 */

$adminPageTitle  = isset($adminPageTitle)  ? h($adminPageTitle) . ' — Admin' : 'Admin — ' . APP_NAME;
$adminActiveNav  = $adminActiveNav ?? '';
$adminUser       = admin_current();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $adminPageTitle ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">

<!-- ── Sidebar ──────────────────────────────────────────────────────────────── -->
<nav class="admin-sidebar" aria-label="Admin navigation">
    <div class="admin-sidebar-header">
        <a href="/admin/" class="admin-logo-link">
            <img src="/assets/logo.png" alt="<?= h(APP_NAME) ?>" class="admin-logo">
        </a>
        <span class="admin-label">Admin</span>
    </div>

    <ul class="admin-nav">
        <li><a href="/admin/" class="admin-nav-link<?= $adminActiveNav === 'dashboard' ? ' active' : '' ?>">📊 Dashboard</a></li>
        <li class="admin-nav-section">Catalogue</li>
        <li><a href="/admin/products.php" class="admin-nav-link<?= $adminActiveNav === 'products' ? ' active' : '' ?>">📦 Products</a></li>
        <li><a href="/admin/categories.php" class="admin-nav-link<?= $adminActiveNav === 'categories' ? ' active' : '' ?>">🗂 Categories</a></li>
        <li><a href="/admin/vendors.php" class="admin-nav-link<?= $adminActiveNav === 'vendors' ? ' active' : '' ?>">🏭 Vendors</a></li>
        <li><a href="/admin/tags.php" class="admin-nav-link<?= $adminActiveNav === 'tags' ? ' active' : '' ?>">🏷 Tags</a></li>
        <li class="admin-nav-section">Sales</li>
        <li><a href="/admin/orders.php" class="admin-nav-link<?= $adminActiveNav === 'orders' ? ' active' : '' ?>">🛒 Orders</a></li>
        <li class="admin-nav-section">Data</li>
        <li><a href="/admin/import-export.php" class="admin-nav-link<?= $adminActiveNav === 'import-export' ? ' active' : '' ?>">⬆⬇ Import / Export</a></li>
        <li class="admin-nav-section">Access</li>
        <li><a href="/admin/users.php" class="admin-nav-link<?= $adminActiveNav === 'users' ? ' active' : '' ?>">👤 Admin users</a></li>
    </ul>

    <div class="admin-sidebar-footer">
        <span class="admin-username">@<?= h($adminUser['username'] ?? '') ?></span>
        <a href="/admin/logout.php" class="admin-logout-link">Sign out</a>
        <a href="/" target="_blank" class="admin-shop-link">← View shop</a>
    </div>
</nav>

<!-- ── Main content ──────────────────────────────────────────────────────────── -->
<div class="admin-content">
