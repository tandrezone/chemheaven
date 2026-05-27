<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Controllers;

use Tandrezone\Chemheaven\Services\AdminAuth;

final class AdminController extends AdminBaseController
{
    public static function loginGet(array $params = []): void
    {
        AdminAuth::startSession();

        if (AdminAuth::check()) {
            self::redirect(self::url());
        }

        $error = (string) ($_GET['error'] ?? '');
        $next = self::sanitize((string) ($_GET['next'] ?? self::BASE), 500);
        $errors = $error !== '' ? [['text' => $error]] : [];

        self::render('administration/login.html', [
            'title' => 'Admin Login — ChemHeaven',
            'errors' => $errors,
            'next' => $next,
        ]);
    }

    public static function loginPost(array $params = []): void
    {
        AdminAuth::startSession();

        if (!self::requireCsrf()) {
            return;
        }

        $username = self::sanitize((string) ($_POST['username'] ?? ''), 100);
        $password = (string) ($_POST['password'] ?? '');
        $next = self::sanitize((string) ($_POST['next'] ?? self::BASE), 500);

        if ($username !== '' && AdminAuth::lockoutMessage($username) !== '') {
            self::redirect(self::url('/login') . '?error=' . urlencode(AdminAuth::lockoutMessage($username)) . '&next=' . urlencode($next));
        }

        if (AdminAuth::attempt($username, $password)) {
            if (!str_starts_with($next, '/admin') && !str_starts_with($next, '/administration')) {
                $next = self::BASE;
            }
            self::redirect($next);
        }

        $error = 'Invalid username or password.';
        if ($username !== '' && ($lock = AdminAuth::lockoutMessage($username)) !== '') {
            $error = $lock;
        }

        self::redirect(self::url('/login') . '?error=' . urlencode($error) . '&next=' . urlencode($next));
    }

    public static function logoutPost(array $params = []): void
    {
        AdminAuth::startSession();

        if (!self::requireCsrf()) {
            return;
        }

        AdminAuth::logout();
        self::redirect(self::url('/login'));
    }

    public static function dashboard(array $params = []): void
    {
        AdminAuth::requireAuth();

        $pdo = self::db();
        $categories = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
        $products = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $gateways = (int) $pdo->query('SELECT COUNT(*) FROM payment_gateways WHERE enabled = 1')->fetchColumn();
        $shipping = (int) $pdo->query('SELECT COUNT(*) FROM shipping_methods WHERE enabled = 1')->fetchColumn();

        self::render('administration/dashboard.html', [
            'title' => 'Dashboard — Administration',
            'page_title' => 'Dashboard',
            'stat_categories' => (string) $categories,
            'stat_products' => (string) $products,
            'stat_gateways' => (string) $gateways,
            'stat_shipping' => (string) $shipping,
        ]);
    }
}
