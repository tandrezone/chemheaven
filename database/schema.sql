-- ChemHeaven Database Schema

CREATE DATABASE IF NOT EXISTS chemheaven CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chemheaven;

-- ── Categories ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid        CHAR(36)     NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_url   VARCHAR(500),
    sort_order  INT          NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Vendors ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vendors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    location   VARCHAR(150) NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Products ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    uuid        CHAR(36)      NOT NULL UNIQUE,
    name        VARCHAR(200)  NOT NULL,
    slug        VARCHAR(200)  NOT NULL UNIQUE,
    description TEXT,
    image_url   VARCHAR(500)  DEFAULT 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg',
    sku         VARCHAR(100),
    featured    TINYINT(1)    NOT NULL DEFAULT 0,
    category_id INT UNSIGNED  NOT NULL,
    vendor_id   INT UNSIGNED  NOT NULL,
    base_price  DECIMAL(10,2) NOT NULL,
    active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (vendor_id)   REFERENCES vendors(id)
);

-- ── Product additional images ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_url  VARCHAR(500) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ── Product variants ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_variants (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    uuid         CHAR(36)      NOT NULL UNIQUE,
    product_id   INT UNSIGNED  NOT NULL,
    weight_label VARCHAR(20)   NOT NULL,   -- display label, e.g. "1g"
    unit         VARCHAR(20)   NOT NULL DEFAULT '',  -- unit field from import/export
    weight_grams DECIMAL(10,3) NOT NULL DEFAULT 0,
    price        DECIMAL(10,2) NOT NULL,
    stock        INT UNSIGNED  NOT NULL DEFAULT 0,
    sku          VARCHAR(100),
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ── Tag groups ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tag_groups (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid       CHAR(36)     NOT NULL UNIQUE,
    name       VARCHAR(100) NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Tags ──────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tags (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid         CHAR(36)     NOT NULL UNIQUE,
    name         VARCHAR(100) NOT NULL,
    color        VARCHAR(20)  NOT NULL DEFAULT '#ffffff',
    bg_color     VARCHAR(20)  NOT NULL DEFAULT '#000000',
    tag_group_id INT UNSIGNED,
    sort_order   INT          NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tag_group_id) REFERENCES tag_groups(id) ON DELETE SET NULL
);

-- ── Product ↔ Tag junction ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_tags (
    product_id INT UNSIGNED NOT NULL,
    tag_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, tag_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)     REFERENCES tags(id)     ON DELETE CASCADE
);

-- ── Orders ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    order_ref      VARCHAR(64)   NOT NULL UNIQUE,
    customer_email VARCHAR(254),
    customer_name  VARCHAR(200),
    total_amount   DECIMAL(10,2) NOT NULL,
    currency       VARCHAR(10)   DEFAULT 'EUR',
    status         ENUM('pending','paid','failed','cancelled') DEFAULT 'pending',
    payment_id     VARCHAR(255),
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Order items ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED  NOT NULL,
    product_id   INT UNSIGNED  NOT NULL,
    variant_id   INT UNSIGNED  NOT NULL,
    product_name VARCHAR(200)  NOT NULL,
    weight_label VARCHAR(20)   NOT NULL,
    price        DECIMAL(10,2) NOT NULL,
    quantity     INT UNSIGNED  NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id)   REFERENCES orders(id)           ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);

-- ── Admin users ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP    NULL
);
