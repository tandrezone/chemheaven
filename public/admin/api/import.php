<?php
/**
 * ChemHeaven — Admin API: Import products from JSON
 *
 * POST /admin/api/import.php?dry_run=1
 * Auth: admin session + X-CSRF-Token header.
 * Body: application/json — ChemHeaven product JSON format.
 *
 * Behaviour:
 *  - Products matched by UUID → update
 *  - Products with unknown UUID → create
 *  - Categories matched by UUID or slug → upsert
 *  - Tags matched by UUID → upsert
 *  - dry_run=1 → validate and report without writing to DB
 *
 * Returns JSON: { ok, created, updated, skipped, errors[] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

header('Content-Type: application/json; charset=utf-8');

function json_fail(string $msg, int $code = 400): never
{
    http_response_code($code);
    exit(json_encode(['ok' => false, 'error' => $msg]));
}

// ── Auth: CSRF ────────────────────────────────────────────────────────────────
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrf_token(), $headerToken)) {
    json_fail('Invalid CSRF token', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('POST required', 405);
}

$dryRun = !empty($_GET['dry_run']) && $_GET['dry_run'] === '1';

// ── Read & parse body ─────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') { json_fail('Empty body'); }
if (strlen($raw) > 10 * 1024 * 1024) { json_fail('Payload too large (max 10 MB)'); }

$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['products']) || !is_array($data['products'])) {
    json_fail('Invalid JSON structure. Expected {"products":[...]}');
}

$db = db();
$created = 0;
$updated = 0;
$skipped = 0;
$errors  = [];

// ── Helper: resolve or create a category ─────────────────────────────────────
function upsert_category(array $cat, bool $dryRun): ?int
{
    if (empty($cat)) return null;
    $uuid = $cat['id'] ?? '';
    $name = mb_substr(trim(strip_tags($cat['name'] ?? '')), 0, 100);
    $slug = mb_substr(trim(strip_tags($cat['slug'] ?? '')), 0, 100);
    $desc = mb_substr(trim(strip_tags($cat['description'] ?? '')), 0, 2000);
    $img  = mb_substr(trim($cat['image'] ?? ''), 0, 500);
    $sort = (int)($cat['sortOrder'] ?? 0);
    $active = !empty($cat['isActive']) ? 1 : 1;

    if ($name === '' || $slug === '') return null;

    // Check by UUID first, then by slug
    $stmt = db()->prepare('SELECT id FROM categories WHERE uuid = :u OR slug = :s LIMIT 1');
    $stmt->execute([':u' => $uuid, ':s' => $slug]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        if (!$dryRun) {
            db()->prepare('UPDATE categories SET name=:n,slug=:s,description=:d,image_url=:img,sort_order=:so,is_active=:a WHERE id=:id')
               ->execute([':n'=>$name,':s'=>$slug,':d'=>$desc,':img'=>$img,':so'=>$sort,':a'=>$active,':id'=>$existing]);
        }
        return (int)$existing;
    }

    if ($dryRun) return -1; // placeholder

    db()->prepare('INSERT INTO categories (uuid,name,slug,description,image_url,sort_order,is_active) VALUES (:uuid,:n,:s,:d,:img,:so,:a)')
       ->execute([':uuid'=>$uuid ?: generate_uuid(),':n'=>$name,':s'=>$slug,':d'=>$desc,':img'=>$img,':so'=>$sort,':a'=>$active]);
    return (int)db()->lastInsertId();
}

// ── Helper: resolve or create a tag ──────────────────────────────────────────
function upsert_tag(array $tag, bool $dryRun): ?int
{
    $uuid    = $tag['id']         ?? '';
    $name    = mb_substr(trim(strip_tags($tag['name']    ?? '')), 0, 100);
    $color   = preg_match('/^#[0-9a-fA-F]{3,6}$/', $tag['color']   ?? '') ? $tag['color']   : '#ffffff';
    $bgColor = preg_match('/^#[0-9a-fA-F]{3,6}$/', $tag['bgColor'] ?? '') ? $tag['bgColor'] : '#000000';
    $sort    = (int)($tag['sortOrder'] ?? 0);

    if ($name === '') return null;

    $stmt = db()->prepare('SELECT id FROM tags WHERE uuid = :u LIMIT 1');
    $stmt->execute([':u' => $uuid]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        if (!$dryRun) {
            db()->prepare('UPDATE tags SET name=:n,color=:c,bg_color=:bg,sort_order=:so WHERE id=:id')
               ->execute([':n'=>$name,':c'=>$color,':bg'=>$bgColor,':so'=>$sort,':id'=>$existing]);
        }
        return (int)$existing;
    }

    if ($dryRun) return -1;

    db()->prepare('INSERT INTO tags (uuid,name,color,bg_color,sort_order) VALUES (:uuid,:n,:c,:bg,:so)')
       ->execute([':uuid'=>$uuid ?: generate_uuid(),':n'=>$name,':c'=>$color,':bg'=>$bgColor,':so'=>$sort]);
    return (int)db()->lastInsertId();
}

// ── Process each product ──────────────────────────────────────────────────────
foreach ($data['products'] as $idx => $p) {
    $ref = '#' . ($idx + 1) . ' ' . ($p['name'] ?? '?');

    try {
        $uuid  = trim($p['id'] ?? '');
        $name  = mb_substr(trim(strip_tags($p['name']  ?? '')), 0, 200);
        $slug  = mb_substr(trim(strip_tags($p['slug']  ?? '')), 0, 200);
        $desc  = mb_substr(trim(strip_tags($p['description'] ?? '')), 0, 5000);
        $img   = mb_substr(trim($p['image'] ?? ''), 0, 500);
        $sku   = mb_substr(trim(strip_tags($p['sku']   ?? '')), 0, 100);
        $active   = !empty($p['isActive'])   ? 1 : 0;
        $featured = !empty($p['isFeatured']) ? 1 : 0;

        if ($name === '' || $slug === '') {
            $errors[] = "{$ref}: name and slug are required.";
            $skipped++;
            continue;
        }
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $errors[] = "{$ref}: invalid slug '{$slug}'.";
            $skipped++;
            continue;
        }

        // ── Category ──────────────────────────────────────────────────────────
        $catData = $p['category'] ?? [];
        $catId   = null;
        if ($catData) {
            $catId = upsert_category($catData, $dryRun);
        }
        if (!$catId) {
            // Try to match by categoryId UUID
            if (!empty($p['categoryId'])) {
                $s = $db->prepare('SELECT id FROM categories WHERE uuid = :u LIMIT 1');
                $s->execute([':u' => $p['categoryId']]);
                $catId = $s->fetchColumn() ?: null;
            }
            if (!$catId) {
                // Use first category as fallback
                $catId = (int)$db->query('SELECT id FROM categories ORDER BY id ASC LIMIT 1')->fetchColumn() ?: null;
            }
        }
        if (!$catId && !$dryRun) {
            $errors[] = "{$ref}: could not resolve category.";
            $skipped++;
            continue;
        }

        // ── Vendor (use first vendor as default for imports) ──────────────────
        $vendorId = (int)$db->query('SELECT id FROM vendors ORDER BY id ASC LIMIT 1')->fetchColumn();
        if (!$vendorId && !$dryRun) {
            $errors[] = "{$ref}: no vendors in database.";
            $skipped++;
            continue;
        }

        // ── Variants ──────────────────────────────────────────────────────────
        $importVariants = [];
        foreach ($p['variants'] ?? [] as $vi => $v) {
            $vLabel  = mb_substr(trim(strip_tags($v['label'] ?? '')), 0, 20);
            $vUnit   = mb_substr(trim(strip_tags($v['unit']  ?? $vLabel)), 0, 20);
            $vPrice  = filter_var($v['price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $vStock  = filter_var($v['stock'] ?? 0, FILTER_VALIDATE_INT);
            $vSku    = mb_substr(trim(strip_tags($v['sku']   ?? '')), 0, 100);
            $vActive = !empty($v['isActive']) ? 1 : 0;
            $vUuid   = trim($v['id'] ?? '');

            if ($vLabel === '' || $vPrice === false || $vPrice < 0) {
                $errors[] = "{$ref} variant #{$vi}: invalid label or price.";
                continue;
            }
            $importVariants[] = [
                'uuid'   => $vUuid ?: generate_uuid(),
                'label'  => $vLabel,
                'unit'   => $vUnit,
                'price'  => (float)$vPrice,
                'stock'  => max(0, (int)$vStock),
                'sku'    => $vSku,
                'active' => $vActive,
            ];
        }

        // Base price from first variant or fallback
        $basePrice = $importVariants[0]['price'] ?? 0.0;

        // ── Upsert product ────────────────────────────────────────────────────
        $existing = null;
        if ($uuid !== '') {
            $s = $db->prepare('SELECT id FROM products WHERE uuid = :u LIMIT 1');
            $s->execute([':u' => $uuid]);
            $existing = $s->fetchColumn() ?: null;
        }
        if (!$existing) {
            // Also try by slug
            $s = $db->prepare('SELECT id FROM products WHERE slug = :s LIMIT 1');
            $s->execute([':s' => $slug]);
            $existing = $s->fetchColumn() ?: null;
        }

        if (!$dryRun) {
            if ($existing) {
                $db->prepare(
                    'UPDATE products SET name=:n,slug=:sl,description=:d,image_url=:img,sku=:sku,
                     featured=:f,active=:a,category_id=:c,vendor_id=:v,base_price=:p
                     WHERE id=:id'
                )->execute([':n'=>$name,':sl'=>$slug,':d'=>$desc,':img'=>$img,':sku'=>$sku,
                    ':f'=>$featured,':a'=>$active,':c'=>$catId,':v'=>$vendorId,
                    ':p'=>$basePrice,':id'=>$existing]);
                $productId = (int)$existing;
                $updated++;
            } else {
                $db->prepare(
                    'INSERT INTO products (uuid,name,slug,description,image_url,sku,featured,active,category_id,vendor_id,base_price)
                     VALUES (:uuid,:n,:sl,:d,:img,:sku,:f,:a,:c,:v,:p)'
                )->execute([':uuid'=>$uuid ?: generate_uuid(),':n'=>$name,':sl'=>$slug,':d'=>$desc,
                    ':img'=>$img,':sku'=>$sku,':f'=>$featured,':a'=>$active,
                    ':c'=>$catId,':v'=>$vendorId,':p'=>$basePrice]);
                $productId = (int)$db->lastInsertId();
                $created++;
            }

            // Sync variants — update by uuid, insert new ones
            $existingVarUuids = [];
            $existingVarsStmt = $db->prepare('SELECT uuid FROM product_variants WHERE product_id = :pid');
            $existingVarsStmt->execute([':pid' => $productId]);
            $existingVarUuids = array_column($existingVarsStmt->fetchAll(), 'uuid');

            $importedUuids = array_column($importVariants, 'uuid');
            // Remove variants not in import
            $toDelete = array_diff($existingVarUuids, $importedUuids);
            foreach ($toDelete as $delUuid) {
                $db->prepare('DELETE FROM product_variants WHERE uuid = :u')->execute([':u' => $delUuid]);
            }

            foreach ($importVariants as $v) {
                if (in_array($v['uuid'], $existingVarUuids, true)) {
                    $db->prepare(
                        'UPDATE product_variants SET weight_label=:l,unit=:u,price=:p,stock=:s,sku=:sku,is_active=:a
                         WHERE uuid=:uuid AND product_id=:pid'
                    )->execute([':l'=>$v['label'],':u'=>$v['unit'],':p'=>$v['price'],
                        ':s'=>$v['stock'],':sku'=>$v['sku'],':a'=>$v['active'],
                        ':uuid'=>$v['uuid'],':pid'=>$productId]);
                } else {
                    $db->prepare(
                        'INSERT INTO product_variants (uuid,product_id,weight_label,unit,weight_grams,price,stock,sku,is_active)
                         VALUES (:uuid,:pid,:l,:u,0,:p,:s,:sku,:a)'
                    )->execute([':uuid'=>$v['uuid'],':pid'=>$productId,':l'=>$v['label'],
                        ':u'=>$v['unit'],':p'=>$v['price'],':s'=>$v['stock'],
                        ':sku'=>$v['sku'],':a'=>$v['active']]);
                }
            }

            // ── Additional images ─────────────────────────────────────────────
            if (!empty($p['images']) && is_array($p['images'])) {
                $db->prepare('DELETE FROM product_images WHERE product_id = :pid')->execute([':pid' => $productId]);
                foreach (array_values($p['images']) as $order => $imgUrl) {
                    $imgUrl = mb_substr(trim($imgUrl), 0, 500);
                    if ($imgUrl !== '') {
                        $db->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (:pid,:img,:so)')
                           ->execute([':pid'=>$productId,':img'=>$imgUrl,':so'=>$order]);
                    }
                }
            }

            // ── Tags ──────────────────────────────────────────────────────────
            $db->prepare('DELETE FROM product_tags WHERE product_id = :pid')->execute([':pid' => $productId]);
            foreach ($p['productTags'] ?? [] as $tagData) {
                $tagId = upsert_tag($tagData, false);
                if ($tagId && $tagId > 0) {
                    $db->prepare('INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?,?)')->execute([$productId, $tagId]);
                }
            }
        } else {
            // Dry run — just count
            $existing ? $updated++ : $created++;
        }

    } catch (\Throwable $e) {
        error_log('[ChemHeaven] Import error at ' . $ref . ': ' . $e->getMessage());
        $errors[] = "{$ref}: internal error (see server log).";
        $skipped++;
    }
}

echo json_encode([
    'ok'      => true,
    'dry_run' => $dryRun,
    'created' => $created,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors'  => $errors,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
