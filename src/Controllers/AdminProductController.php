<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Controllers;

use Tandrezone\Chemheaven\Services\AdminAuth;
use Tandrezone\Chemheaven\Support\Slug;

final class AdminProductController extends AdminBaseController
{
    private const STATUSES = ['active', 'inactive', 'archived'];

    public static function index(array $params = []): void
    {
        AdminAuth::requireAuth();

        $stmt = self::db()->query(
            'SELECT p.id, p.name, p.slug, p.item_code, p.status, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.updated_at DESC, p.id DESC'
        );

        $products = [];
        foreach ($stmt->fetchAll() as $row) {
            $products[] = [
                'id' => (string) $row['id'],
                'name' => htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'),
                'slug' => htmlspecialchars((string) $row['slug'], ENT_QUOTES, 'UTF-8'),
                'item_code' => htmlspecialchars((string) $row['item_code'], ENT_QUOTES, 'UTF-8'),
                'status' => htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8'),
                'category_name' => htmlspecialchars((string) ($row['category_name'] ?? '—'), ENT_QUOTES, 'UTF-8'),
            ];
        }

        self::render('administration/products/index.html', [
            'title' => 'Products — Administration',
            'page_title' => 'Products',
            'products' => $products,
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
        if ($id <= 0) {
            self::flashSet('error', 'Invalid product.');
            self::redirect(self::url('/products'));
        }

        $pdo = self::db();
        $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);

        self::flashSet('success', 'Product deleted.');
        self::redirect(self::url('/products'));
    }

    private static function form(?int $id): void
    {
        $product = [
            'item_code' => '',
            'name' => '',
            'slug' => '',
            'description' => '',
            'category_id' => '',
            'status' => 'active',
        ];
        $variants = [['id' => '', 'sku' => '', 'variant_name' => '', 'price' => '', 'stock_quantity' => '0']];

        if ($id !== null && $id > 0) {
            $pdo = self::db();
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                self::flashSet('error', 'Product not found.');
                self::redirect(self::url('/products'));
            }
            $product = $row;

            $vStmt = $pdo->prepare(
                'SELECT id, sku, variant_name, price, stock_quantity FROM product_variants WHERE product_id = :id ORDER BY id'
            );
            $vStmt->execute(['id' => $id]);
            $variants = $vStmt->fetchAll() ?: $variants;
        }

        $catStmt = self::db()->query('SELECT id, name FROM categories ORDER BY name');
        $categories = [];
        foreach ($catStmt->fetchAll() as $cat) {
            $categories[] = [
                'id' => (string) $cat['id'],
                'name' => htmlspecialchars((string) $cat['name'], ENT_QUOTES, 'UTF-8'),
                'selected' => ((string) ($product['category_id'] ?? '') === (string) $cat['id']) ? 'selected' : '',
            ];
        }

        $variantRows = [];
        foreach ($variants as $idx => $v) {
            $variantRows[] = [
                'index' => (string) $idx,
                'id' => (string) ($v['id'] ?? ''),
                'sku' => htmlspecialchars((string) ($v['sku'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'variant_name' => htmlspecialchars((string) ($v['variant_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'price' => htmlspecialchars((string) ($v['price'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'stock_quantity' => htmlspecialchars((string) ($v['stock_quantity'] ?? '0'), ENT_QUOTES, 'UTF-8'),
            ];
        }

        $statusOptions = [];
        foreach (self::STATUSES as $status) {
            $statusOptions[] = [
                'value' => $status,
                'label' => ucfirst($status),
                'selected' => (($product['status'] ?? 'active') === $status) ? 'selected' : '',
            ];
        }

        self::render('administration/products/form.html', [
            'title' => ($id ? 'Edit' : 'New') . ' Product — Administration',
            'page_title' => $id ? 'Edit product' : 'New product',
            'form_action' => $id ? self::url('/products/' . $id) : self::url('/products'),
            'product_id' => $id ? (string) $id : '',
            'item_code' => htmlspecialchars((string) $product['item_code'], ENT_QUOTES, 'UTF-8'),
            'name' => htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'),
            'slug' => htmlspecialchars((string) $product['slug'], ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars((string) ($product['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'categories' => $categories,
            'status_options' => $statusOptions,
            'variants' => $variantRows,
        ]);
    }

    private static function save(?int $id): void
    {
        $itemCode = self::sanitize((string) ($_POST['item_code'] ?? ''), 100);
        $name = self::sanitize((string) ($_POST['name'] ?? ''), 255);
        $slug = self::sanitize((string) ($_POST['slug'] ?? ''), 255);
        $description = self::sanitize((string) ($_POST['description'] ?? ''), 10000);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $status = self::sanitize((string) ($_POST['status'] ?? 'active'), 20);

        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        if ($name === '' || $itemCode === '') {
            self::flashSet('error', 'Name and item code are required.');
            self::redirect($id ? self::url('/products/' . $id . '/edit') : self::url('/products/new'));
        }

        $slug = $slug !== '' ? Slug::from($slug) : Slug::from($name);
        $categoryId = $categoryId > 0 ? $categoryId : null;

        $rawVariants = $_POST['variants'] ?? [];
        if (!is_array($rawVariants)) {
            $rawVariants = [];
        }

        $pdo = self::db();

        try {
            $pdo->beginTransaction();

            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE products SET item_code = :code, name = :name, slug = :slug,
                     description = :descr, category_id = :cat, status = :status WHERE id = :id'
                );
                $stmt->execute([
                    'code' => $itemCode,
                    'name' => $name,
                    'slug' => $slug,
                    'descr' => $description,
                    'cat' => $categoryId,
                    'status' => $status,
                    'id' => $id,
                ]);
                $productId = $id;
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO products (item_code, name, slug, description, category_id, status)
                     VALUES (:code, :name, :slug, :descr, :cat, :status)'
                );
                $stmt->execute([
                    'code' => $itemCode,
                    'name' => $name,
                    'slug' => $slug,
                    'descr' => $description,
                    'cat' => $categoryId,
                    'status' => $status,
                ]);
                $productId = (int) $pdo->lastInsertId();
            }

            self::syncVariants($pdo, $productId, $rawVariants, $itemCode);

            $pdo->commit();
            self::flashSet('success', $id ? 'Product updated.' : 'Product created.');
        } catch (\PDOException $e) {
            $pdo->rollBack();
            self::flashSet('error', 'Could not save product. Check unique slug, item code, or SKUs.');
            self::redirect($id ? self::url('/products/' . $id . '/edit') : self::url('/products/new'));
        }

        self::redirect(self::url('/products'));
    }

    private static function syncVariants(\PDO $pdo, int $productId, array $rawVariants, string $itemCode): void
    {
        $keepIds = [];
        $index = 0;

        foreach ($rawVariants as $row) {
            if (!is_array($row)) {
                continue;
            }

            $variantId = (int) ($row['id'] ?? 0);
            $sku = self::sanitize((string) ($row['sku'] ?? ''), 100);
            $variantName = self::sanitize((string) ($row['variant_name'] ?? ''), 255);
            $price = (float) ($row['price'] ?? 0);
            $stock = (int) ($row['stock_quantity'] ?? 0);

            if ($sku === '' && $variantName === '') {
                continue;
            }

            if ($sku === '') {
                $sku = strtoupper(substr($itemCode, 0, 50)) . '-' . ($index + 1);
            }

            if ($variantId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE product_variants SET sku = :sku, variant_name = :name, price = :price, stock_quantity = :stock
                     WHERE id = :id AND product_id = :pid'
                );
                $stmt->execute([
                    'sku' => $sku,
                    'name' => $variantName,
                    'price' => $price,
                    'stock' => $stock,
                    'id' => $variantId,
                    'pid' => $productId,
                ]);
                $keepIds[] = $variantId;
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO product_variants (product_id, sku, variant_name, price, stock_quantity)
                     VALUES (:pid, :sku, :name, :price, :stock)'
                );
                $stmt->execute([
                    'pid' => $productId,
                    'sku' => $sku,
                    'name' => $variantName,
                    'price' => $price,
                    'stock' => $stock,
                ]);
                $keepIds[] = (int) $pdo->lastInsertId();
            }

            $index++;
        }

        if ($keepIds === []) {
            $pdo->prepare('DELETE FROM product_variants WHERE product_id = :pid')->execute(['pid' => $productId]);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
        $delete = $pdo->prepare(
            "DELETE FROM product_variants WHERE product_id = ? AND id NOT IN ($placeholders)"
        );
        $delete->execute(array_merge([$productId], $keepIds));
    }
}
