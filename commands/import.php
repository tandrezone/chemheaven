<?php

// Database configuration
$host    = '127.0.0.1';
$db      = 'product_management';
$user    = 'manager';
$pass    = 'manager';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/**
 * Helper to execute Upsert
 */
function upsert(PDO $pdo, string $sql, array $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

// Load JSON data
$jsonData = file_get_contents(__DIR__ . '/../api/products.json');
$data = json_decode($jsonData, true);

if (!$data) {
    die("Error: Invalid JSON format.");
}

foreach ($data as $item) {
    try {
        $pdo->beginTransaction();

        // 1. Upsert Category
        $catId = upsert($pdo, 
            "INSERT INTO categories (name, slug, description) 
             VALUES (:name, :slug, :desc)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)",
            [
                'name' => $item['category']['name'],
                'slug' => $item['category']['slug'],
                'desc' => $item['category']['description'] ?? null
            ]
        );
        
        // If ON DUPLICATE KEY UPDATE didn't change the row, lastInsertId might be 0.
        // We fetch the ID by slug to be sure for the product relation.
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$item['category']['slug']]);
        $catId = $stmt->fetchColumn();

        // 2. Upsert Product
        upsert($pdo,
            "INSERT INTO products (item_code, name, slug, description, category_id, status)
             VALUES (:code, :name, :slug, :desc, :cat_id, :status)
             ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                description = VALUES(description), 
                category_id = VALUES(category_id), 
                status = VALUES(status)",
            [
                'code'   => $item['item_code'],
                'name'   => $item['name'],
                'slug'   => $item['slug'],
                'desc'   => $item['description'] ?? null,
                'cat_id' => $catId,
                'status' => $item['status'] ?? 'active'
            ]
        );

        $stmt = $pdo->prepare("SELECT id FROM products WHERE item_code = ?");
        $stmt->execute([$item['item_code']]);
        $productId = $stmt->fetchColumn();

        // 3. Upsert Measurements
        if (isset($item['measurements'])) {
            upsert($pdo,
                "INSERT INTO product_measurements (product_id, weight_grams, length_mm, width_mm, height_mm, volume_ml)
                 VALUES (:pid, :w, :l, :wi, :h, :v)
                 ON DUPLICATE KEY UPDATE 
                    weight_grams = VALUES(weight_grams), length_mm = VALUES(length_mm), 
                    width_mm = VALUES(width_mm), height_mm = VALUES(height_mm), volume_ml = VALUES(volume_ml)",
                [
                    'pid' => $productId,
                    'w'   => $item['measurements']['weight_grams'] ?? null,
                    'l'   => $item['measurements']['length_mm'] ?? null,
                    'wi'  => $item['measurements']['width_mm'] ?? null,
                    'h'   => $item['measurements']['height_mm'] ?? null,
                    'v'   => $item['measurements']['volume_ml'] ?? null
                ]
            );
        }

        // 4. Upsert Specifications (Encoding Arrays to JSON strings)
        upsert($pdo,
            "INSERT INTO product_specifications (product_id, decoration_settings, sustainability_metrics, technical_specs)
             VALUES (:pid, :decor, :sust, :tech)
             ON DUPLICATE KEY UPDATE 
                decoration_settings = VALUES(decoration_settings), 
                sustainability_metrics = VALUES(sustainability_metrics),
                technical_specs = VALUES(technical_specs)",
            [
                'pid'   => $productId,
                'decor' => json_encode($item['specifications']['decoration'] ?? []),
                'sust'  => json_encode($item['specifications']['sustainability'] ?? []),
                'tech'  => json_encode($item['specifications']['technical'] ?? [])
            ]
        );

        // 5. Upsert Variants
        if (isset($item['variants'])) {
            foreach ($item['variants'] as $variant) {
                upsert($pdo,
                    "INSERT INTO product_variants (product_id, sku, variant_name, price, stock_quantity, attributes)
                     VALUES (:pid, :sku, :vname, :price, :stock, :attr)
                     ON DUPLICATE KEY UPDATE 
                        variant_name = VALUES(variant_name), price = VALUES(price), 
                        stock_quantity = VALUES(stock_quantity), attributes = VALUES(attributes)",
                    [
                        'pid'   => $productId,
                        'sku'   => $variant['sku'],
                        'vname' => $variant['name'],
                        'price' => $variant['price'],
                        'stock' => $variant['stock'],
                        'attr'  => json_encode($variant['attributes'] ?? [])
                    ]
                );
            }
        }

        $pdo->commit();
        echo "Successfully processed: " . $item['item_code'] . "\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Failed to process " . ($item['item_code'] ?? 'Unknown') . ": " . $e->getMessage() . "\n";
    }
}