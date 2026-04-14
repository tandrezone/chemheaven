<?php
/**
 * ChemHeaven — Admin: Product List
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

$db = db();

// Flash message
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

// Search/filter
$search = mb_substr(trim(strip_tags($_GET['q'] ?? '')), 0, 100);
$catId  = ctype_digit($_GET['cat'] ?? '') ? (int)$_GET['cat'] : null;

$where  = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE :s OR p.sku LIKE :s2)';
    $params[':s']  = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
}
if ($catId) {
    $where[] = 'p.category_id = :cat';
    $params[':cat'] = $catId;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$products = $db->prepare(
    "SELECT p.id, p.uuid, p.name, p.slug, p.sku, p.featured, p.active,
            p.base_price, c.name AS cat_name, v.name AS vendor_name,
            (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variant_count
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN vendors v    ON v.id = p.vendor_id
     $whereSQL
     ORDER BY p.id DESC"
);
$products->execute($params);
$products = $products->fetchAll();

$categories     = get_categories();
$adminPageTitle = 'Products';
$adminActiveNav = 'products';

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header">
    <h1>Products</h1>
    <a href="/admin/product-edit.php" class="btn-primary">+ New product</a>
</div>

<?php if ($flash): ?>
    <div class="admin-alert admin-alert--<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<!-- Filter bar -->
<form class="admin-filter-bar" method="get" action="/admin/products.php">
    <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search name or SKU…" maxlength="100">
    <select name="cat">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"<?= $catId === (int)$c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-secondary">Filter</button>
    <?php if ($search || $catId): ?>
        <a href="/admin/products.php" class="btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<div class="admin-card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Vendor</th>
                    <th>Variants</th>
                    <th>Base price</th>
                    <th>Featured</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td class="text-muted"><?= $p['id'] ?></td>
                    <td><strong><?= h($p['name']) ?></strong></td>
                    <td><code><?= h($p['sku'] ?: '—') ?></code></td>
                    <td><?= h($p['cat_name']) ?></td>
                    <td><?= h($p['vendor_name']) ?></td>
                    <td><?= $p['variant_count'] ?></td>
                    <td><?= format_price((float)$p['base_price']) ?></td>
                    <td><?= $p['featured'] ? '⭐' : '—' ?></td>
                    <td><?= $p['active'] ? '<span class="badge-active">Yes</span>' : '<span class="badge-inactive">No</span>' ?></td>
                    <td class="actions-cell">
                        <a href="/admin/product-edit.php?id=<?= $p['id'] ?>" class="btn-sm">Edit</a>
                        <form method="post" action="/admin/product-delete.php" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn-sm btn-danger"
                                onclick="return confirm('Delete product \'<?= h(addslashes($p['name'])) ?>\'?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <tr><td colspan="10" class="text-muted text-center">No products found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
