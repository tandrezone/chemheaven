<?php
/**
 * ChemHeaven — Shared Helpers
 * Privacy-first, security-first utilities.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ── Session ───────────────────────────────────────────────────────────────────

/**
 * Start a secure, hardened PHP session.
 * Must be called before any output.
 */
function session_secure_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cookieParams = [
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',           // current host only
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,         // JS cannot read the session cookie
        'samesite' => 'Lax',        // CSRF mitigation
    ];

    session_name(SESSION_COOKIE_NAME);
    session_set_cookie_params($cookieParams);

    ini_set('session.use_strict_mode',   '1'); // reject unrecognised session IDs
    ini_set('session.use_only_cookies',  '1'); // no session ID in URL
    ini_set('session.use_trans_sid',     '0');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

    session_start();

    // Rotate session ID periodically to limit fixation window.
    if (empty($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
        session_regenerate_id(true);
    } elseif (time() - $_SESSION['_created'] > SESSION_LIFETIME / 2) {
        $_SESSION['_created'] = time();
        session_regenerate_id(true);
    }
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

/** Return (and create if needed) the CSRF token for the current session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_BYTES));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input field. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/**
 * Verify the submitted CSRF token.
 * Aborts with 403 on failure — never silently ignore.
 */
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Invalid request token. Please go back and try again.');
    }
}

// ── Output escaping ───────────────────────────────────────────────────────────

/** HTML-escape a value (XSS prevention). Use on every dynamic output. */
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Cart (session-based, no DB storage) ──────────────────────────────────────

/**
 * Cart structure stored in $_SESSION['cart']:
 * [
 *   variant_id => [
 *     'variant_id'   => int,
 *     'product_id'   => int,
 *     'product_name' => string,
 *     'weight_label' => string,
 *     'price'        => float,
 *     'quantity'     => int,
 *   ],
 *   ...
 * ]
 */

/** Return the current cart array. */
function cart_get(): array
{
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

/** Add an item to the cart or increase its quantity. Returns false if invalid. */
function cart_add(int $variantId, int $qty = 1): bool
{
    if ($qty < 1 || $qty > 999) {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT pv.id, pv.product_id, pv.weight_label, pv.price, pv.stock,
                p.name AS product_name
         FROM product_variants pv
         JOIN products p ON p.id = pv.product_id
         WHERE pv.id = :id AND p.active = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $variantId]);
    $variant = $stmt->fetch();

    if (!$variant) {
        return false;
    }

    $cart = cart_get();
    $key  = (string) $variantId;

    if (isset($cart[$key])) {
        $newQty = $cart[$key]['quantity'] + $qty;
        // Clamp to available stock
        $cart[$key]['quantity'] = min($newQty, (int) $variant['stock']);
    } else {
        $cart[$key] = [
            'variant_id'   => (int) $variant['id'],
            'product_id'   => (int) $variant['product_id'],
            'product_name' => $variant['product_name'],
            'weight_label' => $variant['weight_label'],
            'price'        => (float) $variant['price'],
            'quantity'     => min($qty, (int) $variant['stock']),
        ];
    }

    $_SESSION['cart'] = $cart;
    return true;
}

/** Remove a variant from the cart. */
function cart_remove(int $variantId): void
{
    $key = (string) $variantId;
    unset($_SESSION['cart'][$key]);
}

/** Update quantity of a cart item. Pass 0 or negative to remove. */
function cart_update(int $variantId, int $qty): void
{
    if ($qty <= 0) {
        cart_remove($variantId);
        return;
    }
    $key = (string) $variantId;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] = min($qty, 999);
    }
}

/** Clear the entire cart. */
function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

/** Return the total item count across all cart lines. */
function cart_count(): int
{
    $count = 0;
    foreach (cart_get() as $item) {
        if (!is_array($item)) {
            continue;
        }
        $qty = (int) ($item['quantity'] ?? 0);
        if ($qty > 0) {
            $count += $qty;
        }
    }
    return $count;
}

/** Return the cart grand total. */
function cart_total(): float
{
    $total = 0.0;
    foreach (cart_get() as $item) {
        if (!is_array($item)) {
            continue;
        }
        $price = (float) ($item['price'] ?? 0);
        $qty   = (int) ($item['quantity'] ?? 0);
        if ($price < 0 || $qty <= 0) {
            continue;
        }
        $total += $price * $qty;
    }
    return $total;
}

// ── Products ──────────────────────────────────────────────────────────────────

/** Fetch all active categories. */
function get_categories(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query(
            'SELECT id, name, slug FROM categories ORDER BY name ASC'
        )->fetchAll();
    }
    return $cache;
}

/**
 * Fetch products with optional search and category filter.
 * All user input is passed via prepared statement parameters.
 */
function get_products(?string $search = null, ?int $categoryId = null): array
{
    $where  = ['p.active = 1'];
    $params = [];

    if ($search !== null && $search !== '') {
        $where[]         = '(p.name LIKE :search OR p.description LIKE :search2)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    if ($categoryId !== null) {
        $where[]             = 'p.category_id = :cat';
        $params[':cat'] = $categoryId;
    }

    $sql = 'SELECT p.id, p.name, p.slug, p.description, p.image_url, p.featured,
                   p.base_price, c.name AS category_name, c.slug AS category_slug,
                   v.name AS vendor_name, v.location AS vendor_location
            FROM products p
            JOIN categories c ON c.id = p.category_id
            JOIN vendors v    ON v.id = p.vendor_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.featured DESC, p.id ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Fetch a single product by slug, with all its variants. */
function get_product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.slug, p.description, p.image_url, p.featured,
                p.base_price, c.name AS category_name, c.slug AS category_slug,
                v.name AS vendor_name, v.location AS vendor_location
         FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN vendors v    ON v.id = p.vendor_id
         WHERE p.slug = :slug AND p.active = 1
         LIMIT 1'
    );
    $stmt->execute([':slug' => $slug]);
    $product = $stmt->fetch();

    if (!$product) {
        return null;
    }

    $stmt2 = db()->prepare(
        'SELECT id, weight_label, price, stock
         FROM product_variants
         WHERE product_id = :pid
         ORDER BY weight_grams ASC'
    );
    $stmt2->execute([':pid' => $product['id']]);
    $product['variants'] = $stmt2->fetchAll();

    return $product;
}

/** Fetch variants for a product by ID (used on index cards). */
function get_product_variants(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT id, weight_label, price, stock
         FROM product_variants
         WHERE product_id = :pid
         ORDER BY weight_grams ASC'
    );
    $stmt->execute([':pid' => $productId]);
    return $stmt->fetchAll();
}

// ── Security headers ──────────────────────────────────────────────────────────

/**
 * Send strict HTTP security headers.
 * Call before any output.
 */
function send_security_headers(): void
{
    // Only serve over HTTPS in production; keep permissive for local dev.
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // No external scripts/styles/fonts — privacy-preserving CSP.
    // 'unsafe-inline' for scripts is required for minimal inline JS (price updater etc.)
    header("Content-Security-Policy: default-src 'self'; "
         . "img-src 'self' https://i.ibb.co data:; "
         . "style-src 'self' 'unsafe-inline'; "
         . "script-src 'self' 'unsafe-inline'; "
         . "frame-ancestors 'none'; "
         . "form-action 'self' https://api.oxopay.com https://sandbox.oxopay.com;");

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header('X-XSS-Protection: 0'); // Disabled — CSP is the modern replacement
}

// ── Misc ──────────────────────────────────────────────────────────────────────

/** Generate a RFC 4122 v4 UUID. */
function generate_uuid(): string
{
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** Safe redirect that prevents header injection. */
function safe_redirect(string $url): never
{
    // Only allow relative paths or same-origin URLs.
    if (!str_starts_with($url, '/') && !str_starts_with($url, APP_URL)) {
        $url = '/';
    }
    header('Location: ' . $url, true, 303);
    exit;
}

/** Format a price as a localised currency string. */
function format_price(float $amount, string $currency = '€'): string
{
    return $currency . number_format($amount, 2, '.', ',');
}
