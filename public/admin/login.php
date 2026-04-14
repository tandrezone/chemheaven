<?php
/**
 * ChemHeaven — Admin Login
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();

// Already logged in → go to dashboard
if (admin_is_logged_in()) {
    safe_redirect('/admin/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $result = admin_attempt_login($username, $password);
        if ($result['ok']) {
            safe_redirect('/admin/');
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= h(APP_NAME) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="admin-login-body">

<div class="admin-login-wrap">
    <div class="admin-login-box">
        <img src="/assets/logo.png" alt="<?= h(APP_NAME) ?>" class="admin-login-logo">
        <h1>Admin login</h1>

        <?php if ($error !== ''): ?>
            <div class="admin-alert admin-alert--error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/admin/login.php" class="admin-login-form">
            <?= csrf_field() ?>

            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?= h($_POST['username'] ?? '') ?>"
                autocomplete="username"
                maxlength="100"
                required
                autofocus
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit" class="btn-primary btn-login">Sign in</button>
        </form>
    </div>
</div>

</body>
</html>
