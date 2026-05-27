<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Controllers;

use Tandrezone\Chemheaven\Services\AdminAuth;
use Tandrezone\Chemheaven\Support\Slug;

final class AdminPaymentGatewayController extends AdminBaseController
{
    /** @var string[] */
    private const ALLOWED_DRIVERS = ['oxo'];

    public static function index(array $params = []): void
    {
        AdminAuth::requireAuth();

        $stmt = self::db()->query(
            'SELECT id, code, name, enabled, is_default, sort_order FROM payment_gateways ORDER BY sort_order, id'
        );

        $gateways = [];
        foreach ($stmt->fetchAll() as $row) {
            $gateways[] = [
                'id' => (string) $row['id'],
                'code' => htmlspecialchars((string) $row['code'], ENT_QUOTES, 'UTF-8'),
                'name' => htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'),
                'enabled' => (int) $row['enabled'] ? 'Yes' : 'No',
                'is_default' => (int) $row['is_default'] ? 'Yes' : 'No',
                'sort_order' => (string) $row['sort_order'],
            ];
        }

        self::render('administration/payment-gateways/index.html', [
            'title' => 'Payment Gateways — Administration',
            'page_title' => 'Payment gateways',
            'gateways' => $gateways,
            'allowed_drivers' => implode(', ', self::ALLOWED_DRIVERS),
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
        $count = (int) $pdo->query('SELECT COUNT(*) FROM payment_gateways')->fetchColumn();

        if ($count <= 1) {
            self::flashSet('error', 'At least one payment gateway must remain.');
            self::redirect(self::url('/payment-gateways'));
        }

        $pdo->prepare('DELETE FROM payment_gateways WHERE id = :id')->execute(['id' => $id]);
        self::flashSet('success', 'Payment gateway deleted.');
        self::redirect(self::url('/payment-gateways'));
    }

    private static function form(?int $id): void
    {
        $gateway = [
            'code' => '',
            'name' => '',
            'enabled' => 1,
            'is_default' => 0,
            'sort_order' => 0,
        ];

        if ($id) {
            $stmt = self::db()->prepare('SELECT * FROM payment_gateways WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                self::flashSet('error', 'Gateway not found.');
                self::redirect(self::url('/payment-gateways'));
            }
            $gateway = $row;
        }

        self::render('administration/payment-gateways/form.html', [
            'title' => ($id ? 'Edit' : 'New') . ' Gateway — Administration',
            'page_title' => $id ? 'Edit payment gateway' : 'New payment gateway',
            'form_action' => $id ? self::url('/payment-gateways/' . $id) : self::url('/payment-gateways'),
            'gateway_id' => $id ? (string) $id : '',
            'code' => htmlspecialchars((string) $gateway['code'], ENT_QUOTES, 'UTF-8'),
            'name' => htmlspecialchars((string) $gateway['name'], ENT_QUOTES, 'UTF-8'),
            'enabled_checked' => (int) ($gateway['enabled'] ?? 1) ? 'checked' : '',
            'default_checked' => (int) ($gateway['is_default'] ?? 0) ? 'checked' : '',
            'sort_order' => htmlspecialchars((string) ($gateway['sort_order'] ?? '0'), ENT_QUOTES, 'UTF-8'),
            'code_readonly' => $id ? 'readonly' : '',
            'allowed_drivers' => implode(', ', self::ALLOWED_DRIVERS),
        ]);
    }

    private static function save(?int $id): void
    {
        $code = Slug::from(self::sanitize((string) ($_POST['code'] ?? ''), 50));
        $name = self::sanitize((string) ($_POST['name'] ?? ''), 255);
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($name === '' || $code === '') {
            self::flashSet('error', 'Name and code are required.');
            self::redirect($id ? self::url('/payment-gateways/' . $id . '/edit') : self::url('/payment-gateways/new'));
        }

        if (!in_array($code, self::ALLOWED_DRIVERS, true)) {
            self::flashSet('error', 'Driver code must be one of: ' . implode(', ', self::ALLOWED_DRIVERS));
            self::redirect($id ? self::url('/payment-gateways/' . $id . '/edit') : self::url('/payment-gateways/new'));
        }

        $pdo = self::db();

        try {
            $pdo->beginTransaction();

            if ($isDefault) {
                $pdo->exec('UPDATE payment_gateways SET is_default = 0');
            }

            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE payment_gateways SET name = :name, enabled = :en, is_default = :def, sort_order = :sort WHERE id = :id'
                );
                $stmt->execute(['name' => $name, 'en' => $enabled, 'def' => $isDefault, 'sort' => $sortOrder, 'id' => $id]);
                self::flashSet('success', 'Payment gateway updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO payment_gateways (code, name, enabled, is_default, sort_order, config)
                     VALUES (:code, :name, :en, :def, :sort, :cfg)'
                );
                $stmt->execute([
                    'code' => $code,
                    'name' => $name,
                    'en' => $enabled,
                    'def' => $isDefault,
                    'sort' => $sortOrder,
                    'cfg' => '{}',
                ]);
                self::flashSet('success', 'Payment gateway created.');
            }

            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            self::flashSet('error', 'Could not save gateway. Code may already exist.');
            self::redirect($id ? self::url('/payment-gateways/' . $id . '/edit') : self::url('/payment-gateways/new'));
        }

        self::redirect(self::url('/payment-gateways'));
    }
}
