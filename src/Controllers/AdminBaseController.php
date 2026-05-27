<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Controllers;

use PDO;
use Tandrezone\Chemheaven\Services\Database;
use Tandrezone\Ztemp\TemplateEngine;

abstract class AdminBaseController
{
    public const BASE = '/admin';

    protected static function db(): PDO
    {
        return Database::connection();
    }

    protected static function url(string $path = ''): string
    {
        return self::BASE . $path;
    }

    protected static function normalizeAdminPath(string $path): string
    {
        if (str_starts_with($path, '/administration')) {
            return self::BASE . substr($path, strlen('/administration'));
        }

        return $path;
    }

    protected static function engine(): TemplateEngine
    {
        return new TemplateEngine(__DIR__ . '/../../templates');
    }

    protected static function render(string $template, array $data = []): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $path = self::normalizeAdminPath(
            parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''
        );

        $flashMessages = [];
        $success = self::flashGet('success');
        $error = self::flashGet('error');
        if ($success !== '') {
            $flashMessages[] = ['type' => 'success', 'text' => $success];
        }
        if ($error !== '') {
            $flashMessages[] = ['type' => 'error', 'text' => $error];
        }

        $defaults = [
            'csrf_token' => self::ensureCsrf(),
            'flash_messages' => $flashMessages,
            'admin_username' => htmlspecialchars((string) ($_SESSION['admin_username'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'admin_base' => self::BASE,
            'current_path' => $path,
            'nav_dashboard_active' => $path === self::BASE ? 'active' : '',
            'nav_categories_active' => str_starts_with($path, self::BASE . '/categories') ? 'active' : '',
            'nav_products_active' => str_starts_with($path, self::BASE . '/products') ? 'active' : '',
            'nav_gateways_active' => str_starts_with($path, self::BASE . '/payment-gateways') ? 'active' : '',
            'nav_shipping_active' => str_starts_with($path, self::BASE . '/shipping') ? 'active' : '',
        ];

        echo self::engine()->render($template, array_merge($defaults, $data));
    }

    protected static function redirect(string $url): never
    {
        if (str_starts_with($url, '/administration')) {
            $url = self::BASE . substr($url, strlen('/administration'));
        }

        header('Location: ' . $url);
        exit;
    }

    protected static function ensureCsrf(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return csrf_token();
    }

    protected static function requireCsrf(): bool
    {
        if (!csrf_validate()) {
            http_response_code(403);
            echo 'Invalid or missing security token.';
            return false;
        }

        return true;
    }

    protected static function sanitize(string $value, int $maxLength = 255): string
    {
        $value = trim($value);
        return mb_substr($value, 0, $maxLength);
    }

    protected static function flashSet(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_flash_' . $type] = $message;
    }

    protected static function flashGet(string $type): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = 'admin_flash_' . $type;
        $message = (string) ($_SESSION[$key] ?? '');
        unset($_SESSION[$key]);

        return $message;
    }

    protected static function intParam(array $params, string $key): int
    {
        return max(0, (int) ($params[$key] ?? $_POST[$key] ?? 0));
    }
}
