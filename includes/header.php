<?php
/**
 * ChemHeaven — Shared HTML Header
 * Sends security headers, starts session, and outputs the <head> + nav.
 * $pageTitle and $bodyClass must be set by the including file.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

send_security_headers();
session_secure_start();

$pageTitle = isset($pageTitle) ? h($pageTitle) . ' — ' . APP_NAME : APP_NAME;
$bodyClass = isset($bodyClass) ? h($bodyClass) : '';
$cartCount = cart_count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="ChemHeaven — Quality research chemicals and botanicals.">
    <!-- No external resources: full privacy, no third-party tracking -->
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="<?= $bodyClass ?>">

<header class="site-header">
    <div class="header-inner">
        <a href="/" class="logo-link" aria-label="<?= h(APP_NAME) ?> — Home">
            <img src="/assets/logo.png" alt="<?= h(APP_NAME) ?> logo" class="logo">
        </a>

        <nav class="site-nav" aria-label="Main navigation">
            <a href="/" class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? ' active' : '' ?>">Shop</a>
            <a href="/cart.php" class="nav-link nav-cart<?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? ' active' : '' ?>" aria-label="Cart">
                🛒
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
        </nav>
    </div>
</header>

<main class="site-main">
