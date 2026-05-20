<?php

declare(strict_types=1);

// Database configuration
$host = '127.0.0.1';
$db = 'product_management';
$user = 'manager';
$pass = 'manager';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . PHP_EOL);
}

/**
 * Helper to execute upsert.
 */
function upsert(PDO $pdo, string $sql, array $params): void
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/**
 * Convert a string to a stable slug.
 */
function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'item';
}

/**
 * Build a schema-compatible item code.
 */
function buildItemCode(array $item): string
{
    $code = (string) ($item['item_code'] ?? $item['id'] ?? $item['slug'] ?? '');
    if ($code === '') {
        $code = slugify((string) ($item['name'] ?? 'product'));
    }
    return substr($code, 0, 100);
}

/**
 * Build a schema-compatible SKU for variants.
 */
function buildVariantSku(array $item, array $variant, int $index): string
{
    $raw = (string) ($variant['sku'] ?? $variant['id'] ?? '');
    if ($raw === '') {
        $base = buildItemCode($item);
        $label = (string) ($variant['label'] ?? $variant['name'] ?? ('variant-' . $index));
        $raw = $base . '-' . strtoupper(str_replace('-', '', slugify($label)));
    }
    return substr($raw, 0, 100);
}

/**
 * Normalize whitespace for display text blocks.
 */
function normalizeText(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
}

/**
 * Return optional reference metadata for compounds when available.
 */
function referenceProfile(string $name, string $slug): array
{
    $needle = strtolower(trim($name . ' ' . $slug));

    $profiles = [
        [
            'match' => ['4-mmc', 'mephedrone'],
            'formal_name' => '2-(methylamino)-1-(4-methylphenyl)-1-propanone, monohydrochloride',
            'cas' => '1189726-22-4',
            'molecular_formula' => 'C11H15NO • HCl',
            'formula_weight' => '213.7',
            'class' => 'cathinone',
        ],
        [
            'match' => ['a-pvp', 'pyrrolidinopentiophenone'],
            'formal_name' => '1-phenyl-2-(1-pyrrolidinyl)-1-pentanone, monohydrochloride',
            'cas' => '5485-65-4',
            'molecular_formula' => 'C15H21NO • HCl',
            'formula_weight' => '267.8',
            'class' => 'cathinone',
        ],
        [
            'match' => ['2-mmc', '2-methylmethcathinone'],
            'formal_name' => '2-methylmethcathinone (2-MMC)',
            'class' => 'cathinone',
        ],
        [
            'match' => ['nep'],
            'formal_name' => 'N-ethylpentedrone (NEP)',
            'class' => 'cathinone',
        ],
        [
            'match' => ['clonazolam'],
            'formal_name' => '6-(2-chlorophenyl)-1-methyl-8-nitro-4H-[1,2,4]triazolo[4,3-a][1,4]benzodiazepine',
            'class' => 'triazolobenzodiazepine',
        ],
        [
            'match' => ['5-meo-dipt', 'foxy'],
            'formal_name' => '5-methoxy-N,N-diisopropyltryptamine',
            'class' => 'tryptamine',
        ],
        [
            'match' => ['lsd-25', 'lysergic acid diethylamide'],
            'formal_name' => 'Lysergic acid diethylamide',
            'class' => 'lysergamide',
        ],
        [
            'match' => ['1v-lsd'],
            'formal_name' => '1-valeroyl-lysergic acid diethylamide',
            'class' => 'lysergamide',
        ],
        [
            'match' => ['o-pce', '2-oxo-pce'],
            'formal_name' => '2-oxo-PCE (O-PCE)',
            'class' => 'arylcyclohexylamine',
        ],
        [
            'match' => ['2f-dck', "2'fi-oxo-pcm"],
            'formal_name' => '2-(2-fluorophenyl)-2-(methylamino)cyclohexan-1-one',
            'class' => 'arylcyclohexylamine',
        ],
        [
            'match' => ['2-fxe'],
            'formal_name' => '2-FXE',
            'class' => 'arylcyclohexylamine',
        ],
        [
            'match' => ['ketamine'],
            'formal_name' => '2-(2-chlorophenyl)-2-(methylamino)cyclohexan-1-one',
            'class' => 'arylcyclohexylamine',
        ],
        [
            'match' => ['5f-mdmb-pinaca', '5f-adb'],
            'formal_name' => '5F-MDMB-PINACA',
            'class' => 'synthetic cannabinoid',
        ],
        [
            'match' => ['7-hydroxymitragynine'],
            'formal_name' => '7-hydroxymitragynine',
            'class' => 'indole alkaloid',
        ],
        [
            'match' => ['amt succinate'],
            'formal_name' => 'alpha-methyltryptamine succinate',
            'class' => 'tryptamine',
        ],
        [
            'match' => ['pagoclone'],
            'formal_name' => 'Pagoclone',
            'class' => 'cyclopyrrolone',
        ],
        [
            'match' => ['ro5-4864'],
            'formal_name' => '4\'-chlorodiazepam (Ro5-4864)',
            'class' => 'benzodiazepine-site ligand',
        ],
        [
            'match' => ['mxpcp'],
            'formal_name' => 'MXPCP',
            'class' => 'arylcyclohexylamine',
        ],
        [
            'match' => ['n-dipropyl-dimethocaine', 'dpdmc'],
            'formal_name' => 'N-dipropyl-dimethocaine',
            'class' => 'local anesthetic analogue',
        ],
        [
            'match' => ['bromonordiazepam'],
            'formal_name' => 'Bromonordiazepam',
            'class' => 'benzodiazepine analogue',
        ],
        [
            'match' => ['psilocybe cubensis'],
            'formal_name' => 'Psilocybe cubensis biomass',
            'class' => 'tryptamine-containing fungal material',
        ],
        [
            'match' => ['hashassine hash'],
            'formal_name' => 'Cannabinoid-rich hashish matrix',
            'class' => 'cannabinoid preparation',
        ],
    ];

    foreach ($profiles as $profile) {
        foreach ($profile['match'] as $token) {
            if (str_contains($needle, $token)) {
                return $profile;
            }
        }
    }

    return [];
}

/**
 * Build a consistent technical-style description from source info.
 */
function buildStyledDescription(array $item): string
{
    $name = trim((string) ($item['name'] ?? 'Unknown Product'));
    $slug = trim((string) ($item['slug'] ?? ''));
    $category = is_array($item['category'] ?? null) ? $item['category'] : [];
    $categoryName = trim((string) ($category['name'] ?? 'reference'));
    $originalDescription = normalizeText((string) ($item['description'] ?? ''));

    $profile = referenceProfile($name, $slug);
    $compoundClass = (string) ($profile['class'] ?? strtolower($categoryName));
    $formalName = (string) ($profile['formal_name'] ?? $name);
    $cas = (string) ($profile['cas'] ?? 'Not specified in source listing');
    $molecularFormula = (string) ($profile['molecular_formula'] ?? 'Not specified in source listing');
    $formulaWeight = (string) ($profile['formula_weight'] ?? 'Not specified in source listing');

    $dosageInfo = '';
    if (preg_match('/\b\d+(?:[\.,]\d+)?\s*(?:mg|mcg|ug|µg|g|kg|ml|mL)\b/i', $originalDescription, $match) === 1) {
        $dosageInfo = $match[0];
    }

    $formulation = 'Not specified in source listing';
    if (str_contains(strtolower($originalDescription), 'tablet') || str_contains(strtolower($name), 'blotter')) {
        $formulation = 'tablets/blotters';
    } elseif (str_contains(strtolower($originalDescription), 'pellet')) {
        $formulation = 'pellets';
    } elseif (str_contains(strtolower($originalDescription), 'liquid')) {
        $formulation = 'liquid preparation';
    } elseif (str_contains(strtolower($originalDescription), 'crystal')) {
        $formulation = 'crystalline solid';
    } elseif (str_contains(strtolower($name), 'cubensis')) {
        $formulation = 'dried fungal biomass';
    }

    $purity = 'Not specified in source listing';
    if (preg_match('/≥\s*\d+(?:\.\d+)?%|\+\/-\s*\d+%|\b\d+(?:\.\d+)?%/u', $originalDescription, $match) === 1) {
        $purity = $match[0];
    }

    $intro = sprintf(
        '%s is an analytical reference material categorized as a %s compound in the %s class.',
        $name,
        $compoundClass,
        $categoryName
    );

    $sourceLine = $originalDescription !== ''
        ? 'Source Notes: ' . $originalDescription
        : 'Source Notes: No legacy description text was provided in the source record.';

    $dosageLine = $dosageInfo !== ''
        ? 'Reference Dose/Strength: ' . $dosageInfo
        : 'Reference Dose/Strength: Not specified in source listing';

    return implode("\n", [
        $intro,
        $sourceLine,
        'Formal Name: ' . $formalName,
        'CAS Number: ' . $cas,
        'Molecular Formula: ' . $molecularFormula,
        'Formula Weight: ' . $formulaWeight,
        'Purity: ' . $purity,
        'Formulation: ' . $formulation,
        $dosageLine,
    ]);
}

// Load JSON data
$jsonPath = __DIR__ . '/../api/products.json';
$jsonData = file_get_contents($jsonPath);
if ($jsonData === false) {
    die("Error: Could not read JSON file at $jsonPath" . PHP_EOL);
}

$decoded = json_decode($jsonData, true);
if (!is_array($decoded)) {
    die("Error: Invalid JSON format." . PHP_EOL);
}

// Support both {"products":[...]} and plain array payloads.
$products = $decoded['products'] ?? $decoded;
if (!is_array($products)) {
    die("Error: No importable products found in JSON." . PHP_EOL);
}

foreach ($products as $item) {
    if (!is_array($item)) {
        continue;
    }

    $productName = (string) ($item['name'] ?? 'Unnamed Product');
    $productSlug = (string) ($item['slug'] ?? slugify($productName));
    $itemCode = buildItemCode($item);
    $styledDescription = buildStyledDescription($item);

    $category = is_array($item['category'] ?? null) ? $item['category'] : [];
    $categoryName = (string) ($category['name'] ?? 'Uncategorized');
    $categorySlug = (string) ($category['slug'] ?? slugify($categoryName));
    $categoryDescription = $category['description'] ?? null;

    $status = isset($item['isActive']) ? ((bool) $item['isActive'] ? 'active' : 'inactive') : ((string) ($item['status'] ?? 'active'));
    if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
        $status = 'active';
    }

    try {
        $pdo->beginTransaction();

        // 1. Upsert Category
        upsert(
            $pdo,
            "INSERT INTO categories (name, slug, description)
             VALUES (:name, :slug, :descr)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description)",
            [
                'name' => $categoryName,
                'slug' => $categorySlug,
                'descr' => $categoryDescription,
            ]
        );

        $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $categorySlug]);
        $catId = (int) $stmt->fetchColumn();

        // 2. Upsert Product
        upsert(
            $pdo,
            "INSERT INTO products (item_code, name, slug, description, category_id, status)
             VALUES (:code, :name, :slug, :descr, :cat_id, :status)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                slug = VALUES(slug),
                description = VALUES(description),
                category_id = VALUES(category_id),
                status = VALUES(status)",
            [
                'code' => $itemCode,
                'name' => $productName,
                'slug' => $productSlug,
                'descr' => $styledDescription,
                'cat_id' => $catId > 0 ? $catId : null,
                'status' => $status,
            ]
        );

        $stmt = $pdo->prepare('SELECT id FROM products WHERE item_code = :code LIMIT 1');
        $stmt->execute(['code' => $itemCode]);
        $productId = (int) $stmt->fetchColumn();

        if ($productId <= 0) {
            throw new RuntimeException('Could not resolve product id after upsert.');
        }

        // 3. Upsert Measurements (optional)
        if (is_array($item['measurements'] ?? null)) {
            $measurements = $item['measurements'];
            upsert(
                $pdo,
                "INSERT INTO product_measurements (product_id, weight_grams, length_mm, width_mm, height_mm, volume_ml)
                 VALUES (:pid, :w, :l, :wi, :h, :v)
                 ON DUPLICATE KEY UPDATE
                    weight_grams = VALUES(weight_grams),
                    length_mm = VALUES(length_mm),
                    width_mm = VALUES(width_mm),
                    height_mm = VALUES(height_mm),
                    volume_ml = VALUES(volume_ml)",
                [
                    'pid' => $productId,
                    'w' => $measurements['weight_grams'] ?? null,
                    'l' => $measurements['length_mm'] ?? null,
                    'wi' => $measurements['width_mm'] ?? null,
                    'h' => $measurements['height_mm'] ?? null,
                    'v' => $measurements['volume_ml'] ?? null,
                ]
            );
        }

        // 4. Upsert Specifications (optional)
        $specifications = is_array($item['specifications'] ?? null) ? $item['specifications'] : [];
        upsert(
            $pdo,
            "INSERT INTO product_specifications (product_id, decoration_settings, sustainability_metrics, technical_specs)
             VALUES (:pid, :decor, :sust, :tech)
             ON DUPLICATE KEY UPDATE
                decoration_settings = VALUES(decoration_settings),
                sustainability_metrics = VALUES(sustainability_metrics),
                technical_specs = VALUES(technical_specs)",
            [
                'pid' => $productId,
                'decor' => json_encode($specifications['decoration'] ?? [], JSON_UNESCAPED_UNICODE),
                'sust' => json_encode($specifications['sustainability'] ?? [], JSON_UNESCAPED_UNICODE),
                'tech' => json_encode($specifications['technical'] ?? [], JSON_UNESCAPED_UNICODE),
            ]
        );

        // 5. Upsert Variants
        if (is_array($item['variants'] ?? null)) {
            foreach (array_values($item['variants']) as $index => $variant) {
                if (!is_array($variant)) {
                    continue;
                }

                $variantName = (string) ($variant['name'] ?? $variant['label'] ?? ('Variant ' . ($index + 1)));
                $variantPrice = (float) ($variant['price'] ?? 0);
                $variantStock = (int) ($variant['stock'] ?? $variant['stock_quantity'] ?? 0);
                $variantSku = buildVariantSku($item, $variant, $index);

                $attributes = [];
                if (isset($variant['attributes']) && is_array($variant['attributes'])) {
                    $attributes = $variant['attributes'];
                } else {
                    $attributes = [
                        'source_id' => $variant['id'] ?? null,
                        'unit' => $variant['unit'] ?? null,
                    ];
                }

                upsert(
                    $pdo,
                    "INSERT INTO product_variants (product_id, sku, variant_name, price, stock_quantity, attributes)
                     VALUES (:pid, :sku, :vname, :price, :stock, :attr)
                     ON DUPLICATE KEY UPDATE
                        variant_name = VALUES(variant_name),
                        price = VALUES(price),
                        stock_quantity = VALUES(stock_quantity),
                        attributes = VALUES(attributes)",
                    [
                        'pid' => $productId,
                        'sku' => $variantSku,
                        'vname' => $variantName,
                        'price' => $variantPrice,
                        'stock' => $variantStock,
                        'attr' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
                    ]
                );
            }
        }

        $pdo->commit();
        echo "Successfully processed: " . $itemCode . PHP_EOL;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Failed to process " . $itemCode . ': ' . $e->getMessage() . PHP_EOL;
    }
}