-- ChemHeaven Database Schema

CREATE DATABASE IF NOT EXISTS chemheaven CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chemheaven;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vendors table
CREATE TABLE IF NOT EXISTS vendors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    image_url VARCHAR(500) DEFAULT 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg',
    featured TINYINT(1) DEFAULT 0,
    category_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);

-- Product weight variants table
CREATE TABLE IF NOT EXISTS product_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    weight_label VARCHAR(20) NOT NULL,   -- e.g. "1g", "5g", "10g", "25g", "250g"
    weight_grams DECIMAL(10,3) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT UNSIGNED DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_ref VARCHAR(64) NOT NULL UNIQUE,
    customer_email VARCHAR(254),
    customer_name VARCHAR(200),
    total_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'EUR',
    status ENUM('pending','paid','failed','cancelled') DEFAULT 'pending',
    payment_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    weight_label VARCHAR(20) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);
