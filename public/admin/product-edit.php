<?php
/**
 * ChemHeaven — Admin: Create / Edit Product
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

$db = db();

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$product   = null;
$variants  = [];
$productTagIds = [];

if ($productId) {
    $product = $db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $product->execute([':id' => $productId]);
    $product = $product->fetch();
    if (!$product) { safe_redirect('/admin/products.php'); }

    $variants = $db->prepare(
        'SELECT * FROM product_variants WHERE product_id = :pid ORDER BY weight_grams ASC'
    );
    $variants->execute([':pid' => $productId]);
    $variants = $variants->fetchAll();

    $ptStmt = $db->prepare('SELECT tag_id FROM product_tags WHERE product_id = :pid');
    $ptStmt->execute([':pid' => $productId]);
    $productTagIds = array_column($ptStmt->fetchAll(), 'tag_id');
}

$categories = get_categories();
$vendors    = $db->query('SELECT id, name FROM vendors ORDER BY name ASC')->fetchAll();
$allTags    = $db->query('SELECT id, name, color, bg_color FROM tags ORDER BY name ASC')->fetchAll();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // ── Collect & validate inputs ─────────────────────────────────────────────
    $name       = mb_substr(trim(strip_tags($_POST['name'] ?? '')), 0, 200);
    $slug       = mb_substr(trim(strip_tags($_POST['slug'] ?? '')), 0, 200);
    $desc       = mb_substr(trim(strip_tags($_POST['description'] ?? '')), 0, 5000);
    $imageUrl   = mb_substr(trim($_POST['image_url'] ?? ''), 0, 500);
    $sku        = mb_substr(trim(strip_tags($_POST['sku'] ?? '')), 0, 100);
    $catId      = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $vendorId   = filter_input(INPUT_POST, 'vendor_id',   FILTER_VALIDATE_INT);
    $basePrice  = filter_input(INPUT_POST, 'base_price',  FILTER_VALIDATE_FLOAT);
    $featured   = !empty($_POST['featured']) ? 1 : 0;
    $active     = !empty($_POST['active'])   ? 1 : 0;
    $selectedTags = array_map('intval', (array)($_POST['tags'] ?? []));

    if ($name === '')   $errors[] = 'Product name is required.';
    if ($slug === '')   $errors[] = 'Slug is required.';
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) $errors[] = 'Slug may only contain lowercase letters, digits and hyphens.';
    if (!$catId)        $errors[] = 'Category is required.';
    if (!$vendorId)     $errors[] = 'Vendor is required.';
    if ($basePrice === false || $basePrice < 0) $errors[] = 'Invalid base price.';

    // Variant rows submitted as arrays
    $variantLabels  = $_POST['v_label']    ?? [];
    $variantUnits   = $_POST['v_unit']     ?? [];
    $variantGrams   = $_POST['v_grams']    ?? [];
    $variantPrices  = $_POST['v_price']    ?? [];
    $variantStocks  = $_POST['v_stock']    ?? [];
    $variantSkus    = $_POST['v_sku']      ?? [];
    $variantActives = $_POST['v_active']   ?? [];
    $variantIds     = $_POST['v_id']       ?? [];

    $cleanVariants = [];
    foreach ($variantLabels as $i => $label) {
        $label = mb_substr(trim(strip_tags($label)), 0, 20);
        if ($label === '') continue;
        $price = filter_var($variantPrices[$i] ?? 0, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($variantStocks[$i] ?? 0, FILTER_VALIDATE_INT);
        $grams = filter_var($variantGrams[$i]  ?? 0, FILTER_VALIDATE_FLOAT);
        if ($price === false || $price < 0) { $errors[] = "Invalid price for variant '{$label}'."; continue; }
        if ($stock === false || $stock < 0) { $errors[] = "Invalid stock for variant '{$label}'."; continue; }
        $cleanVariants[] = [
            'id'       => ctype_digit((string)($variantIds[$i] ?? '')) ? (int)$variantIds[$i] : null,
            'label'    => $label,
            'unit'     => mb_substr(trim(strip_tags($variantUnits[$i] ?? $label)), 0, 20),
            'grams'    => max(0, (float)$grams),
            'price'    => (float)$price,
            'stock'    => max(0, (int)$stock),
            'sku'      => mb_substr(trim(strip_tags($variantSkus[$i] ?? '')), 0, 100),
            'is_active'=> isset($variantActives[$i]) ? 1 : 0,
        ];
    }

    if (empty($errors)) {
        if ($productId) {
            // Update existing product
            $db->prepare(
                'UPDATE products SET name=:n, slug=:sl, description=:d, image_url=:img,
                 sku=:sku, featured=:f, active=:a, category_id=:c, vendor_id=:v, base_price=:p
                 WHERE id=:id'
            )->execute([
                ':n'=>$name,':sl'=>$slug,':d'=>$desc,':img'=>$imageUrl,
                ':sku'=>$sku,':f'=>$featured,':a'=>$active,
                ':c'=>$catId,':v'=>$vendorId,':p'=>$basePrice,':id'=>$productId,
            ]);
        } else {
            // Insert new product
            $db->prepare(
                'INSERT INTO products (uuid,name,slug,description,image_url,sku,featured,active,category_id,vendor_id,base_price)
                 VALUES (:uuid,:n,:sl,:d,:img,:sku,:f,:a,:c,:v,:p)'
            )->execute([
                ':uuid'=>generate_uuid(),':n'=>$name,':sl'=>$slug,':d'=>$desc,
                ':img'=>$imageUrl,':sku'=>$sku,':f'=>$featured,':a'=>$active,
                ':c'=>$catId,':v'=>$vendorId,':p'=>$basePrice,
            ]);
            $productId = (int)$db->lastInsertId();
        }

        // ── Sync variants ─────────────────────────────────────────────────────
        $existingVarIds = array_filter(array_column($cleanVariants, 'id'));

        // Delete removed variants
        if ($existingVarIds) {
            $in = implode(',', array_fill(0, count($existingVarIds), '?'));
            $stmt = $db->prepare("DELETE FROM product_variants WHERE product_id = ? AND id NOT IN ($in)");
            $stmt->execute(array_merge([$productId], $existingVarIds));
        } else {
            $db->prepare('DELETE FROM product_variants WHERE product_id = ?')
               ->execute([$productId]);
        }

        foreach ($cleanVariants as $v) {
            if ($v['id']) {
                $db->prepare(
                    'UPDATE product_variants SET weight_label=:l,unit=:u,weight_grams=:g,
                     price=:p,stock=:s,sku=:sku,is_active=:a WHERE id=:id AND product_id=:pid'
                )->execute([':l'=>$v['label'],':u'=>$v['unit'],':g'=>$v['grams'],
                    ':p'=>$v['price'],':s'=>$v['stock'],':sku'=>$v['sku'],
                    ':a'=>$v['is_active'],':id'=>$v['id'],':pid'=>$productId]);
            } else {
                $db->prepare(
                    'INSERT INTO product_variants (uuid,product_id,weight_label,unit,weight_grams,price,stock,sku,is_active)
                     VALUES (:uuid,:pid,:l,:u,:g,:p,:s,:sku,:a)'
                )->execute([':uuid'=>generate_uuid(),':pid'=>$productId,':l'=>$v['label'],
                    ':u'=>$v['unit'],':g'=>$v['grams'],':p'=>$v['price'],
                    ':s'=>$v['stock'],':sku'=>$v['sku'],':a'=>$v['is_active']]);
            }
        }

        // ── Sync tags ─────────────────────────────────────────────────────────
        $db->prepare('DELETE FROM product_tags WHERE product_id = ?')->execute([$productId]);
        foreach ($selectedTags as $tid) {
            $db->prepare('INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?,?)')->execute([$productId, $tid]);
        }

        $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Product saved successfully.'];
        safe_redirect('/admin/products.php');
    }

    // Re-populate on error
    $product = array_merge($product ?? [], compact('name','slug','desc','imageUrl','sku','catId','vendorId','basePrice','featured','active'));
    $productTagIds = $selectedTags;
    $variants = $cleanVariants;
}

$adminPageTitle = $productId ? 'Edit product' : 'New product';
$adminActiveNav = 'products';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header">
    <h1><?= $productId ? 'Edit product' : 'New product' ?></h1>
    <a href="/admin/products.php" class="btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <ul class="admin-alert admin-alert--error">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="/admin/product-edit.php<?= $productId ? '?id='.$productId : '' ?>" class="admin-form">
    <?= csrf_field() ?>

    <div class="admin-form-grid">
        <!-- Left column -->
        <div class="admin-form-main">
            <div class="admin-card">
                <h2>Basic information</h2>
                <label>Name *
                    <input type="text" name="name" value="<?= h($product['name'] ?? '') ?>" required maxlength="200">
                </label>
                <label>Slug *
                    <input type="text" name="slug" value="<?= h($product['slug'] ?? '') ?>" required maxlength="200" pattern="[a-z0-9\-]+">
                    <small>Lowercase, hyphens only. Used in product URLs.</small>
                </label>
                <label>SKU
                    <input type="text" name="sku" value="<?= h($product['sku'] ?? '') ?>" maxlength="100">
                </label>
                <label>Description
                    <textarea name="description" rows="4" maxlength="5000"><?= h($product['description'] ?? '') ?></textarea>
                </label>
                <label>Image URL
                    <input type="url" name="image_url" value="<?= h($product['image_url'] ?? '') ?>" maxlength="500">
                </label>
            </div>

            <!-- Variants -->
            <div class="admin-card" id="variants-card">
                <div class="admin-card-header">
                    <h2>Variants (weights)</h2>
                    <button type="button" class="btn-secondary btn-sm" id="add-variant">+ Add variant</button>
                </div>
                <div class="table-wrap">
                    <table class="admin-table" id="variants-table">
                        <thead>
                            <tr>
                                <th>Label</th>
                                <th>Unit</th>
                                <th>Grams</th>
                                <th>Price (€)</th>
                                <th>Stock</th>
                                <th>SKU</th>
                                <th>Active</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="variants-body">
                        <?php
                        $varRows = !empty($cleanVariants) ? $cleanVariants : $variants;
                        foreach ($varRows as $i => $v): ?>
                            <tr class="variant-row">
                                <td><input type="hidden" name="v_id[]" value="<?= (int)($v['id'] ?? 0) ?>">
                                    <input type="text" name="v_label[]" value="<?= h($v['weight_label'] ?? $v['label'] ?? '') ?>" required maxlength="20" class="input-sm"></td>
                                <td><input type="text" name="v_unit[]" value="<?= h($v['unit'] ?? '') ?>" maxlength="20" class="input-sm"></td>
                                <td><input type="number" name="v_grams[]" value="<?= h($v['weight_grams'] ?? $v['grams'] ?? 0) ?>" step="0.001" min="0" class="input-sm"></td>
                                <td><input type="number" name="v_price[]" value="<?= h($v['price'] ?? '') ?>" step="0.01" min="0" required class="input-sm"></td>
                                <td><input type="number" name="v_stock[]" value="<?= (int)($v['stock'] ?? 0) ?>" min="0" class="input-sm"></td>
                                <td><input type="text" name="v_sku[]" value="<?= h($v['sku'] ?? '') ?>" maxlength="100" class="input-sm"></td>
                                <td><input type="checkbox" name="v_active[]" value="1"<?= ($v['is_active'] ?? 1) ? ' checked' : '' ?>></td>
                                <td><button type="button" class="btn-sm btn-danger remove-variant">✕</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="admin-form-sidebar">
            <div class="admin-card">
                <h2>Publishing</h2>
                <label class="label-inline">
                    <input type="checkbox" name="featured" value="1"<?= !empty($product['featured']) ? ' checked' : '' ?>>
                    Featured product
                </label>
                <label class="label-inline">
                    <input type="checkbox" name="active" value="1"<?= ($product['active'] ?? 1) ? ' checked' : '' ?>>
                    Active (visible in shop)
                </label>
                <label>Base price (€) *
                    <input type="number" name="base_price" value="<?= h($product['base_price'] ?? '') ?>" step="0.01" min="0" required>
                </label>
            </div>

            <div class="admin-card">
                <h2>Category & vendor</h2>
                <label>Category *
                    <select name="category_id" required>
                        <option value="">— select —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"<?= (($product['category_id'] ?? null) == $c['id']) ? ' selected' : '' ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Vendor *
                    <select name="vendor_id" required>
                        <option value="">— select —</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>"<?= (($product['vendor_id'] ?? null) == $v['id']) ? ' selected' : '' ?>><?= h($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <?php if (!empty($allTags)): ?>
            <div class="admin-card">
                <h2>Tags</h2>
                <div class="tag-checkboxes">
                    <?php foreach ($allTags as $tag): ?>
                        <label class="label-inline tag-label" style="--tag-color:<?= h($tag['color']) ?>;--tag-bg:<?= h($tag['bg_color']) ?>">
                            <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>"
                                <?= in_array((int)$tag['id'], $productTagIds, true) ? ' checked' : '' ?>>
                            <span class="tag-chip" style="color:<?= h($tag['color']) ?>;background:<?= h($tag['bg_color']) ?>"><?= h($tag['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary">Save product</button>
        <a href="/admin/products.php" class="btn-secondary">Cancel</a>
    </div>
</form>

<!-- Variant row template (JS) -->
<template id="variant-row-tpl">
    <tr class="variant-row">
        <td><input type="hidden" name="v_id[]" value="0">
            <input type="text" name="v_label[]" placeholder="e.g. 10g" required maxlength="20" class="input-sm"></td>
        <td><input type="text" name="v_unit[]" placeholder="e.g. 10g" maxlength="20" class="input-sm"></td>
        <td><input type="number" name="v_grams[]" value="0" step="0.001" min="0" class="input-sm"></td>
        <td><input type="number" name="v_price[]" placeholder="0.00" step="0.01" min="0" required class="input-sm"></td>
        <td><input type="number" name="v_stock[]" value="0" min="0" class="input-sm"></td>
        <td><input type="text" name="v_sku[]" placeholder="SKU" maxlength="100" class="input-sm"></td>
        <td><input type="checkbox" name="v_active[]" value="1" checked></td>
        <td><button type="button" class="btn-sm btn-danger remove-variant">✕</button></td>
    </tr>
</template>

<script>
(function () {
    document.getElementById('add-variant').addEventListener('click', function () {
        var tpl  = document.getElementById('variant-row-tpl').content.cloneNode(true);
        document.getElementById('variants-body').appendChild(tpl);
    });
    document.getElementById('variants-body').addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-variant')) {
            e.target.closest('tr').remove();
        }
    });
    // Auto-fill unit from label
    document.getElementById('variants-body').addEventListener('input', function (e) {
        if (e.target.name === 'v_label[]') {
            var row  = e.target.closest('tr');
            var unit = row.querySelector('[name="v_unit[]"]');
            if (unit && unit.value === '') unit.value = e.target.value;
        }
    });
}());
</script>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
