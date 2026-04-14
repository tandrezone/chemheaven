<?php
/**
 * ChemHeaven — Main Shop Page
 * Displays product cards with search and category filtering.
 * All filtering is done via GET parameters, validated server-side.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

// ── Input validation ──────────────────────────────────────────────────────────
// Sanitise search: strip tags, limit length.
$rawSearch  = isset($_GET['q']) ? trim(strip_tags($_GET['q'])) : '';
$search     = mb_substr($rawSearch, 0, 100);

// Sanitise category filter: must be a positive integer.
$rawCat     = $_GET['cat'] ?? '';
$categoryId = ctype_digit($rawCat) && (int)$rawCat > 0 ? (int)$rawCat : null;

// ── Data ──────────────────────────────────────────────────────────────────────
$categories = get_categories();
$products   = get_products($search !== '' ? $search : null, $categoryId);

// Pre-fetch variants for all products in one loop to avoid N+1
$variantMap = [];
foreach ($products as $p) {
    $variantMap[$p['id']] = get_product_variants((int)$p['id']);
}

// ── Render ────────────────────────────────────────────────────────────────────
$pageTitle = 'Shop';
$bodyClass = 'page-shop';
require __DIR__ . '/../includes/header.php';
?>

<!-- ── Search bar ──────────────────────────────────────────────────────────── -->
<section class="search-section">
    <form class="search-form" method="get" action="/" role="search">
        <label for="search-input" class="sr-only">Search products</label>
        <input
            id="search-input"
            type="search"
            name="q"
            value="<?= h($search) ?>"
            placeholder="Search products…"
            maxlength="100"
            autocomplete="off"
        >
        <?php if ($categoryId): ?>
            <input type="hidden" name="cat" value="<?= $categoryId ?>">
        <?php endif; ?>
        <button type="submit">Search</button>
    </form>
</section>

<!-- ── Category filter ─────────────────────────────────────────────────────── -->
<section class="category-section" aria-label="Filter by category">
    <a
        href="<?= $search !== '' ? '/?q=' . urlencode($search) : '/' ?>"
        class="category-btn<?= $categoryId === null ? ' active' : '' ?>"
    >All</a>
    <?php foreach ($categories as $cat): ?>
        <?php
        $catUrl = '/?cat=' . $cat['id'];
        if ($search !== '') {
            $catUrl .= '&q=' . urlencode($search);
        }
        ?>
        <a
            href="<?= h($catUrl) ?>"
            class="category-btn<?= $categoryId === (int)$cat['id'] ? ' active' : '' ?>"
        ><?= h($cat['name']) ?></a>
    <?php endforeach; ?>
</section>

<!-- ── Product grid ────────────────────────────────────────────────────────── -->
<section class="product-grid" aria-label="Products">
    <?php if (empty($products)): ?>
        <p class="no-results">No products found<?= $search !== '' ? ' for "<strong>' . h($search) . '</strong>"' : '' ?>.</p>
    <?php endif; ?>

    <?php foreach ($products as $product):
        $variants = $variantMap[$product['id']] ?? [];
        $first    = $variants[0] ?? null;
    ?>

    <div class="product-card">

        <!-- ── Top: image + meta ── -->
        <div class="card-top">
            <?php if ($product['featured']): ?>
                <span class="badge-featured">Featured</span>
            <?php endif; ?>

            <img
                src="<?= h($product['image_url']) ?>"
                alt="<?= h($product['name']) ?>"
                class="card-image"
                loading="lazy"
            >

            <div class="card-meta">
                <span class="card-category"><?= h($product['category_name']) ?></span>
                <span class="card-location"><?= h($product['vendor_location']) ?></span>
            </div>
        </div>

        <!-- ── Bottom: info + actions ── -->
        <div class="card-body">
            <a href="/product.php?slug=<?= urlencode($product['slug']) ?>" class="card-title-link">
                <h3><?= h($product['name']) ?></h3>
            </a>

            <p class="card-desc"><?= h($product['description']) ?></p>

            <!-- Weight variant selector -->
            <?php if (!empty($variants)): ?>
            <form class="add-to-cart-form" method="post" action="/cart-action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="_ref" value="shop">

                <div class="variant-buttons" role="group" aria-label="Select weight">
                    <?php foreach ($variants as $i => $v): ?>
                        <label class="variant-label<?= $i === 0 ? ' selected' : '' ?>">
                            <input
                                type="radio"
                                name="variant_id"
                                value="<?= (int)$v['id'] ?>"
                                <?= $i === 0 ? 'checked' : '' ?>
                                <?= (int)$v['stock'] === 0 ? 'disabled' : '' ?>
                                class="variant-radio"
                            >
                            <?= h($v['weight_label']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p class="card-vendor">by <?= h($product['vendor_name']) ?></p>

                <div class="card-footer">
                    <span class="card-price" id="price-<?= (int)$product['id'] ?>">
                        <?= format_price((float)($first['price'] ?? $product['base_price'])) ?>
                    </span>
                    <button type="submit" class="btn-add">+ Add</button>
                </div>
            </form>
            <?php endif; ?>
        </div>

    </div><!-- /.product-card -->

    <?php endforeach; ?>
</section>

<!-- Minimal JS: update displayed price when user selects a weight variant -->
<script>
(function () {
    var prices = <?= json_encode(
        array_map(
            fn($p) => array_column(
                $variantMap[$p['id']] ?? [],
                'price',
                'id'
            ),
            $products
        ),
        JSON_THROW_ON_ERROR
    ) ?>;
    var productIds = <?= json_encode(array_column($products, 'id'), JSON_THROW_ON_ERROR) ?>;

    document.querySelectorAll('.add-to-cart-form').forEach(function (form, idx) {
        var pid   = productIds[idx];
        var pMap  = prices[idx] || {};
        var priceEl = document.getElementById('price-' + pid);

        form.querySelectorAll('.variant-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var p = pMap[this.value];
                if (priceEl && p !== undefined) {
                    priceEl.textContent = '\u20AC' + parseFloat(p).toFixed(2);
                }
                // Update selected styling
                form.querySelectorAll('.variant-label').forEach(function (lbl) {
                    lbl.classList.remove('selected');
                });
                this.parentElement.classList.add('selected');
            });
        });
    });
}());
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
