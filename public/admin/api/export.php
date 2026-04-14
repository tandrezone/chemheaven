<?php
/**
 * ChemHeaven — Admin API: Export products as JSON
 *
 * GET /admin/api/export.php
 * Auth: admin session + CSRF (via X-CSRF-Token header for JS fetch).
 *
 * Returns the full product catalogue in the ChemHeaven JSON format.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

// Verify CSRF token from header (JS fetch sends it via header, not form body)
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrf_token(), $headerToken)) {
    http_response_code(403);
    exit('{"error":"Invalid CSRF token"}');
}

$db = db();

// ── Fetch products with all related data ──────────────────────────────────────
$products = $db->query(
    'SELECT p.*, c.uuid AS cat_uuid, c.name AS cat_name, c.slug AS cat_slug,
            c.description AS cat_desc, c.image_url AS cat_image,
            c.sort_order AS cat_sort_order, c.is_active AS cat_is_active,
            c.created_at AS cat_created_at
     FROM products p
     JOIN categories c ON c.id = p.category_id
     ORDER BY p.created_at ASC'
)->fetchAll();

$output = ['products' => []];

foreach ($products as $p) {
    // Variants
    $variants = $db->prepare(
        'SELECT * FROM product_variants WHERE product_id = :pid ORDER BY weight_grams ASC'
    );
    $variants->execute([':pid' => $p['id']]);
    $variantRows = $variants->fetchAll();

    $variantOut = array_map(fn($v) => [
        'id'        => $v['uuid'],
        'productId' => $p['uuid'],
        'label'     => $v['weight_label'],
        'unit'      => $v['unit'],
        'price'     => (float)$v['price'],
        'stock'     => (int)$v['stock'],
        'sku'       => $v['sku'] ?? '',
        'isActive'  => (bool)$v['is_active'],
    ], $variantRows);

    // Tags
    $tagStmt = $db->prepare(
        'SELECT t.uuid, t.name, t.color, t.bg_color, t.sort_order, t.created_at,
                tg.uuid AS tag_group_uuid
         FROM product_tags pt
         JOIN tags t ON t.id = pt.tag_id
         LEFT JOIN tag_groups tg ON tg.id = t.tag_group_id
         WHERE pt.product_id = :pid
         ORDER BY t.sort_order ASC'
    );
    $tagStmt->execute([':pid' => $p['id']]);
    $tagRows = $tagStmt->fetchAll();

    $tagUuids = array_column($tagRows, 'uuid');
    $productTagsOut = array_map(fn($t) => [
        'id'         => $t['uuid'],
        'name'       => $t['name'],
        'color'      => $t['color'],
        'bgColor'    => $t['bg_color'],
        'tagGroupId' => $t['tag_group_uuid'] ?? '',
        'sortOrder'  => (int)$t['sort_order'],
        'createdAt'  => $t['created_at'],
    ], $tagRows);

    // Additional images
    $imgStmt = $db->prepare(
        'SELECT image_url FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC'
    );
    $imgStmt->execute([':pid' => $p['id']]);
    $images = array_column($imgStmt->fetchAll(), 'image_url');

    $output['products'][] = [
        'id'          => $p['uuid'],
        'name'        => $p['name'],
        'slug'        => $p['slug'],
        'description' => $p['description'],
        'categoryId'  => $p['cat_uuid'],
        'image'       => $p['image_url'],
        'images'      => $images,
        'tags'        => $tagUuids,
        'isActive'    => (bool)$p['active'],
        'isFeatured'  => (bool)$p['featured'],
        'sku'         => $p['sku'] ?? '',
        'createdAt'   => $p['created_at'],
        'updatedAt'   => $p['updated_at'],
        'variants'    => $variantOut,
        'category'    => [
            'id'          => $p['cat_uuid'],
            'name'        => $p['cat_name'],
            'slug'        => $p['cat_slug'],
            'description' => $p['cat_desc'],
            'image'       => $p['cat_image'],
            'sortOrder'   => (int)$p['cat_sort_order'],
            'isActive'    => (bool)$p['cat_is_active'],
            'createdAt'   => $p['cat_created_at'],
        ],
        'productTags' => $productTagsOut,
    ];
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="chemheaven-products-' . date('Y-m-d') . '.json"');
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
