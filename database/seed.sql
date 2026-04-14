-- ChemHeaven Seed Data
USE chemheaven;

-- Categories
INSERT INTO categories (name, slug) VALUES
    ('Cannabinoids', 'cannabinoids'),
    ('Psychedelics', 'psychedelics'),
    ('Nootropics', 'nootropics'),
    ('Research Chemicals', 'research-chemicals'),
    ('Botanicals', 'botanicals');

-- Vendors
INSERT INTO vendors (name, location) VALUES
    ('AlphaLab', 'Amsterdam, NL'),
    ('SynthCore', 'Berlin, DE'),
    ('PureChem', 'Zurich, CH'),
    ('NovaBio', 'Prague, CZ');

-- Products
INSERT INTO products (name, slug, description, image_url, featured, category_id, vendor_id, base_price) VALUES
    ('CBD Isolate 99%', 'cbd-isolate-99', 'Our signature CBD isolate, the purest on the market. Lab-tested, pharmaceutical grade.', 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 1, 1, 1, 9.99),
    ('CBG Powder', 'cbg-powder', 'High-purity CBG powder sourced from premium hemp. Great for research and formulation.', 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 1, 2, 14.99),
    ('Psilocybin Mushroom Extract', 'psilocybin-extract', 'Standardised psilocybin mushroom extract. For research purposes only.', 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 1, 2, 3, 24.99),
    ('Lion\'s Mane Extract', 'lions-mane-extract', 'Premium lion\'s mane mushroom extract, 30% polysaccharides. Supports cognitive function.', 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 5, 4, 19.99),
    ('Aniracetam', 'aniracetam', 'High-purity aniracetam powder, a classic nootropic compound.', 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 3, 1, 12.99),
    ('2-FMA', '2-fma', 'Research-grade 2-fluoromethamphetamine. Laboratory use only.', 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 4, 2, 29.99),
    ('Kratom Extract 50x', 'kratom-extract-50x', 'Ultra-concentrated Kratom extract, 50x potency from premium Borneo leaves.', 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 1, 5, 3, 18.99),
    ('Alpha-GPC 99%', 'alpha-gpc-99', 'Pharmaceutical-grade Alpha-GPC for cognitive enhancement research.', 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 3, 4, 22.99);

-- Product Variants (weight options per product)
-- CBD Isolate
INSERT INTO product_variants (product_id, weight_label, weight_grams, price, stock) VALUES
    (1, '1g',   1,   9.99,  100),
    (1, '5g',   5,  39.99,   80),
    (1, '10g',  10,  69.99,   50),
    (1, '25g',  25, 149.99,   30),
    (1, '250g', 250, 999.99,  10);

-- CBG Powder
INSERT INTO product_variants (product_id, weight_label, weight_grams, price, stock) VALUES
    (2, '1g',   1,  14.99, 100),
    (2, '5g',   5,  59.99,  60),
    (2, '10g',  10, 109.99,  40),
    (2, '25g',  25, 239.99,  20),
    (2, '250g', 250,1799.99,  5);

-- Psilocybin Extract
INSERT INTO product_variants (product_id, weight_label, weight_grams, price, stock) VALUES
    (3, '1g',   1,  24.99,  80),
    (3, '5g',   5,  99.99,  40),
    (3, '10g',  10, 179.99,  20),
    (3, '25g',  25, 399.99,  10),
    (3, '250g', 250,2999.99,  2);

-- Lion's Mane
INSERT INTO product_variants (product_id, weight_label, weight_grams, price, stock) VALUES
    (4, '1g',   1,  19.99, 100),
    (4, '5g',   5,  79.99,  70),
    (4, '10g',  10, 139.99,  50),
    (4, '25g',  25, 299.99,  25),
    (4, '250g', 250,2199.99,  5);

-- Aniracetam
INSERT INTO product_variants (product_id, weight_label, weight_grams, price, stock) VALUES
    (5, '1g',   1,  12.99, 120),
    (5, '5g',   5,  49.99,  80),
    (5, '10g',  10,  89.99,  60),
    (5, '25g',  25, 199.99,  30),
    (5, '250g', 250,1499.99,  8);

-- 2-FMA
INSERT INTO product_variants (product_id, weight_label, weight_grams, price, stock) VALUES
    (6, '1g',   1,  29.99,  60),
    (6, '5g',   5, 119.99,  30),
    (6, '10g',  10, 219.99,  15),
    (6, '25g',  25, 499.99,   8),
    (6, '250g', 250,3999.99,  2);

-- Kratom Extract
INSERT INTO product_variants (product_id, weight_label, weight_grams, price, stock) VALUES
    (7, '1g',   1,  18.99, 100),
    (7, '5g',   5,  74.99,  60),
    (7, '10g',  10, 134.99,  40),
    (7, '25g',  25, 299.99,  20),
    (7, '250g', 250,2299.99,  4);

-- Alpha-GPC
INSERT INTO product_variants (product_id, weight_label, weight_grams, price, stock) VALUES
    (8, '1g',   1,  22.99, 100),
    (8, '5g',   5,  89.99,  70),
    (8, '10g',  10, 159.99,  50),
    (8, '25g',  25, 349.99,  25),
    (8, '250g', 250,2699.99,  5);
