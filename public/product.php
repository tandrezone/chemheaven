<?php
/**
 * ChemHeaven — Product Detail Page
 * Accessed via /product.php?slug=product-slug
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

// ── Input validation ──────────────────────────────────────────────────────────
$rawSlug = $_GET['slug'] ?? '';
// Slugs: lowercase letters, digits, hyphens only — reject anything else.
$slug = preg_match('/^[a-z0-9\-]{1,200}$/', $rawSlug) ? $rawSlug : '';

if ($slug === '') {
    http_response_code(404);
    safe_redirect('/');
}

$product = get_product_by_slug($slug);

if (!$product) {
    http_response_code(404);
    safe_redirect('/');
}

$variants = $product['variants'];

// ── Render ────────────────────────────────────────────────────────────────────
$pageTitle = $product['name'];
$bodyClass = 'page-product';
require __DIR__ . '/../includes/header.php';
?>

<div class="product-detail">

    <div class="product-card product-card--detail">

        <!-- ── Top: image + meta ── -->
        <div class="card-top">
            <?php if ($product['featured']): ?>
                <span class="badge-featured">Featured</span>
            <?php endif; ?>

            <img
                src="<?= h($product['image_url']) ?>"
                alt="<?= h($product['name']) ?>"
                class="card-image card-image--large"
            >

            <div class="card-meta">
                <span class="card-category"><?= h($product['category_name']) ?></span>
                <span class="card-location"><?= h($product['vendor_location']) ?></span>
            </div>
        </div>

        <!-- ── Bottom: info + actions ── -->
        <div class="card-body">
            <h1 class="product-detail-title"><?= h($product['name']) ?></h1>
            <p class="card-desc"><?= h($product['description']) ?></p>

            <?php if (!empty($variants)): ?>
            <form class="add-to-cart-form" method="post" action="/cart-action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">

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
                    <span class="card-price" id="price-detail">
                        <?= format_price((float)$variants[0]['price']) ?>
                    </span>
                    <button type="submit" class="btn-add">+ Add to cart</button>
                </div>
            </form>
            <?php endif; ?>
        </div>

    </div><!-- /.product-card -->

    <div class="back-link-wrap">
        <a href="/" class="back-link">&larr; Back to shop</a>
    </div>

</div><!-- /.product-detail -->

<!-- Minimal JS: update price display on variant change -->
<script>
(function () {
    var pMap = <?= json_encode(
        array_column($variants, 'price', 'id'),
        JSON_THROW_ON_ERROR
    ) ?>;
    var priceEl = document.getElementById('price-detail');
    document.querySelectorAll('.variant-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var p = pMap[this.value];
            if (priceEl && p !== undefined) {
                priceEl.textContent = '\u20AC' + parseFloat(p).toFixed(2);
            }
            document.querySelectorAll('.variant-label').forEach(function (lbl) {
                lbl.classList.remove('selected');
            });
            this.parentElement.classList.add('selected');
        });
    });
}());
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
