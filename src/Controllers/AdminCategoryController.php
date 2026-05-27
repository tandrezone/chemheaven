<?php

declare(strict_types=1);

namespace Tandrezone\Chemheaven\Controllers;

use Tandrezone\Chemheaven\Services\AdminAuth;
use Tandrezone\Chemheaven\Support\Slug;

final class AdminCategoryController extends AdminBaseController
{
    public static function index(array $params = []): void
    {
        AdminAuth::requireAuth();

        $stmt = self::db()->query(
            'SELECT c.id, c.name, c.slug, c.description,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c
             ORDER BY c.name ASC'
        );

        $categories = [];
        foreach ($stmt->fetchAll() as $row) {
            $categories[] = [
                'id' => (string) $row['id'],
                'name' => htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'),
                'slug' => htmlspecialchars((string) $row['slug'], ENT_QUOTES, 'UTF-8'),
                'product_count' => (string) $row['product_count'],
            ];
        }

        self::render('administration/categories/index.html', [
            'title' => 'Categories — Administration',
            'page_title' => 'Categories',
            'categories' => $categories,
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
        $id = self::intParam($params, 'id');
        self::form($id);
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
            self::flashSet('error', 'Invalid category.');
            self::redirect(self::url('/categories'));
        }

        $pdo = self::db();
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);

        self::flashSet('success', 'Category deleted.');
        self::redirect(self::url('/categories'));
    }

    private static function form(?int $id): void
    {
        $category = ['name' => '', 'slug' => '', 'description' => ''];

        if ($id !== null && $id > 0) {
            $stmt = self::db()->prepare('SELECT id, name, slug, description FROM categories WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                self::flashSet('error', 'Category not found.');
                self::redirect(self::url('/categories'));
            }
            $category = $row;
        }

        self::render('administration/categories/form.html', [
            'title' => ($id ? 'Edit' : 'New') . ' Category — Administration',
            'page_title' => $id ? 'Edit category' : 'New category',
            'form_action' => $id ? self::url('/categories/' . $id) : self::url('/categories'),
            'category_id' => $id ? (string) $id : '',
            'name' => htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'),
            'slug' => htmlspecialchars((string) $category['slug'], ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars((string) ($category['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'is_edit' => $id ? '1' : '',
        ]);
    }

    private static function save(?int $id): void
    {
        $name = self::sanitize((string) ($_POST['name'] ?? ''), 255);
        $slug = self::sanitize((string) ($_POST['slug'] ?? ''), 255);
        $description = self::sanitize((string) ($_POST['description'] ?? ''), 5000);

        if ($name === '') {
            self::flashSet('error', 'Name is required.');
            self::redirect($id ? self::url('/categories/' . $id . '/edit') : self::url('/categories/new'));
        }

        if ($slug === '') {
            $slug = Slug::from($name);
        } else {
            $slug = Slug::from($slug);
        }

        $pdo = self::db();

        try {
            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE categories SET name = :name, slug = :slug, description = :descr WHERE id = :id'
                );
                $stmt->execute(['name' => $name, 'slug' => $slug, 'descr' => $description, 'id' => $id]);
                self::flashSet('success', 'Category updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :descr)'
                );
                $stmt->execute(['name' => $name, 'slug' => $slug, 'descr' => $description]);
                self::flashSet('success', 'Category created.');
            }
        } catch (\PDOException $e) {
            self::flashSet('error', 'Could not save category. Slug may already exist.');
            self::redirect($id ? self::url('/categories/' . $id . '/edit') : self::url('/categories/new'));
        }

        self::redirect(self::url('/categories'));
    }
}
