<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Controllers;

use Tandrezone\Chemheaven\Services\AdminAuth;
use Tandrezone\Chemheaven\Support\Slug;

final class AdminShippingController extends AdminBaseController
{
    public static function index(array $params = []): void
    {
        AdminAuth::requireAuth();

        $stmt = self::db()->query(
            'SELECT id, code, name, description, price, enabled, sort_order
             FROM shipping_methods ORDER BY sort_order, id'
        );

        $methods = [];
        foreach ($stmt->fetchAll() as $row) {
            $methods[] = [
                'id' => (string) $row['id'],
                'code' => htmlspecialchars((string) $row['code'], ENT_QUOTES, 'UTF-8'),
                'name' => htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'),
                'description' => htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'price' => number_format((float) $row['price'], 2),
                'enabled' => (int) $row['enabled'] ? 'Yes' : 'No',
                'sort_order' => (string) $row['sort_order'],
            ];
        }

        self::render('administration/shipping/index.html', [
            'title' => 'Shipping — Administration',
            'page_title' => 'Shipping methods',
            'methods' => $methods,
        ]);
    }

    public static function createForm(array $params = []): void
    {
        AdminAuth::requireAuth();
        self::form(null);
    }

    public static function editForm(array $params = []): void
    {
        AdminAuth::requireAuth();
        self::form(self::intParam($params, 'id'));
    }

    public static function store(array $params = []): void
    {
        AdminAuth::requireAuth();
        if (!self::requireCsrf()) {
            return;
        }
        self::save(null);
    }

    public static function update(array $params = []): void
    {
        AdminAuth::requireAuth();
        if (!self::requireCsrf()) {
            return;
        }
        self::save(self::intParam($params, 'id'));
    }

    public static function delete(array $params = []): void
    {
        AdminAuth::requireAuth();
        if (!self::requireCsrf()) {
            return;
        }

        $id = self::intParam($params, 'id');
        $pdo = self::db();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM shipping_methods')->fetchColumn();

        if ($count <= 1) {
            self::flashSet('error', 'At least one shipping method must remain.');
            self::redirect('/admin/shipping');
        }

        $pdo->prepare('DELETE FROM shipping_methods WHERE id = :id')->execute(['id' => $id]);
        self::flashSet('success', 'Shipping method deleted.');
        self::redirect('/admin/shipping');
    }

    private static function form(?int $id): void
    {
        $method = [
            'code' => '',
            'name' => '',
            'description' => '',
            'price' => '0.00',
            'enabled' => 1,
            'sort_order' => 0,
        ];

        if ($id) {
            $stmt = self::db()->prepare('SELECT * FROM shipping_methods WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                self::flashSet('error', 'Shipping method not found.');
                self::redirect('/admin/shipping');
            }
            $method = $row;
        }

        self::render('administration/shipping/form.html', [
            'title' => ($id ? 'Edit' : 'New') . ' Shipping — Administration',
            'page_title' => $id ? 'Edit shipping method' : 'New shipping method',
            'form_action' => $id ? '/admin/shipping/' . $id : '/admin/shipping',
            'method_id' => $id ? (string) $id : '',
            'code' => htmlspecialchars((string) $method['code'], ENT_QUOTES, 'UTF-8'),
            'name' => htmlspecialchars((string) $method['name'], ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars((string) ($method['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'price' => htmlspecialchars((string) $method['price'], ENT_QUOTES, 'UTF-8'),
            'enabled_checked' => (int) ($method['enabled'] ?? 1) ? 'checked' : '',
            'sort_order' => htmlspecialchars((string) ($method['sort_order'] ?? '0'), ENT_QUOTES, 'UTF-8'),
            'code_readonly' => $id ? 'readonly' : '',
        ]);
    }

    private static function save(?int $id): void
    {
        $code = $id
            ? self::sanitize((string) ($_POST['code'] ?? ''), 50)
            : Slug::from(self::sanitize((string) ($_POST['code'] ?? ''), 50));
        $name = self::sanitize((string) ($_POST['name'] ?? ''), 255);
        $description = self::sanitize((string) ($_POST['description'] ?? ''), 500);
        $price = max(0, (float) ($_POST['price'] ?? 0));
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($name === '' || $code === '') {
            self::flashSet('error', 'Name and code are required.');
            self::redirect($id ? '/admin/shipping/' . $id . '/edit' : '/admin/shipping/new');
        }

        $pdo = self::db();

        try {
            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE shipping_methods SET name = :name, description = :descr, price = :price,
                     enabled = :en, sort_order = :sort WHERE id = :id'
                );
                $stmt->execute([
                    'name' => $name,
                    'descr' => $description,
                    'price' => $price,
                    'en' => $enabled,
                    'sort' => $sortOrder,
                    'id' => $id,
                ]);
                self::flashSet('success', 'Shipping method updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO shipping_methods (code, name, description, price, enabled, sort_order)
                     VALUES (:code, :name, :descr, :price, :en, :sort)'
                );
                $stmt->execute([
                    'code' => $code,
                    'name' => $name,
                    'descr' => $description,
                    'price' => $price,
                    'en' => $enabled,
                    'sort' => $sortOrder,
                ]);
                self::flashSet('success', 'Shipping method created.');
            }
        } catch (\PDOException $e) {
            self::flashSet('error', 'Could not save shipping method. Code may already exist.');
            self::redirect($id ? '/admin/shipping/' . $id . '/edit' : '/admin/shipping/new');
        }

        self::redirect('/admin/shipping');
    }
}
