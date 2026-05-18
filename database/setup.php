<?php

// Database configuration
$host    = '127.0.0.1';
$db      = 'product_management';
$user    = 'manager';
$pass    = 'manager';
$charset = 'utf8mb4';

// Initial connection without DB name to create the database first
$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 1. Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db` ");
    echo "Database '$db' initialized.\n";

    // 2. Define Table Schemas
    $tables = [
        "categories" => "
            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

        "products" => "
            CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_code VARCHAR(100) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                description TEXT,
                category_id INT,
                status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

        "product_measurements" => "
            CREATE TABLE IF NOT EXISTS product_measurements (
                product_id INT PRIMARY KEY,
                weight_grams DECIMAL(10, 2),
                length_mm DECIMAL(10, 2),
                width_mm DECIMAL(10, 2),
                height_mm DECIMAL(10, 2),
                volume_ml DECIMAL(10, 2),
                CONSTRAINT fk_measurements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",

        "product_specifications" => "
            CREATE TABLE IF NOT EXISTS product_specifications (
                product_id INT PRIMARY KEY,
                decoration_settings JSON,
                sustainability_metrics JSON,
                technical_specs JSON,
                CONSTRAINT fk_specs_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                CONSTRAINT check_json_decoration CHECK (JSON_VALID(decoration_settings)),
                CONSTRAINT check_json_sustainability CHECK (JSON_VALID(sustainability_metrics))
            ) ENGINE=InnoDB",

        "product_variants" => "
            CREATE TABLE IF NOT EXISTS product_variants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                sku VARCHAR(100) UNIQUE NOT NULL,
                variant_name VARCHAR(255),
                price DECIMAL(12, 2),
                stock_quantity INT DEFAULT 0,
                attributes JSON,
                CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
    ];

    // 3. Execute Table Creation
    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "Table '$name' checked/created successfully.\n";
    }

    // 4. Add Indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_item_code ON products(item_code)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_variant_sku ON product_variants(sku)");
    echo "Performance indexes applied.\n";

    echo "\nSchema setup complete. You can now run the import script.\n";

} catch (\PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}