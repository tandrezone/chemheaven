-- ChemHeaven Seed Data
USE chemheaven;

-- Categories
INSERT INTO categories (uuid, name, slug, description, sort_order) VALUES
    (UUID(), 'Cannabinoids',       'cannabinoids',       'Hemp-derived cannabinoid compounds.',        1),
    (UUID(), 'Psychedelics',       'psychedelics',       'Psychedelic research compounds.',            2),
    (UUID(), 'Nootropics',         'nootropics',         'Cognitive enhancement compounds.',           3),
    (UUID(), 'Research Chemicals', 'research-chemicals', 'Novel research chemical compounds.',         4),
    (UUID(), 'Botanicals',         'botanicals',         'Natural plant-derived compounds.',           5);

-- Vendors
INSERT INTO vendors (name, location) VALUES
    ('AlphaLab',  'Amsterdam, NL'),
    ('SynthCore', 'Berlin, DE'),
    ('PureChem',  'Zurich, CH'),
    ('NovaBio',   'Prague, CZ');

-- Products
INSERT INTO products (uuid, name, slug, description, image_url, featured, category_id, vendor_id, base_price, sku) VALUES
    (UUID(), 'CBD Isolate 99%',          'cbd-isolate-99',      'Our signature CBD isolate, the purest on the market. Lab-tested, pharmaceutical grade.',                 'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 1, 1, 1,  9.99, 'CBD-ISO-99'),
    (UUID(), 'CBG Powder',               'cbg-powder',          'High-purity CBG powder sourced from premium hemp. Great for research and formulation.',                   'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 1, 2, 14.99, 'CBG-PWD'),
    (UUID(), 'Psilocybin Mushroom Extract','psilocybin-extract','Standardised psilocybin mushroom extract. For research purposes only.',                                   'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 1, 2, 3, 24.99, 'PSI-EXT'),
    (UUID(), 'Lion\'s Mane Extract',     'lions-mane-extract',  'Premium lion\'s mane mushroom extract, 30% polysaccharides. Supports cognitive function.',               'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 5, 4, 19.99, 'LME-30P'),
    (UUID(), 'Aniracetam',               'aniracetam',          'High-purity aniracetam powder, a classic nootropic compound.',                                            'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 3, 1, 12.99, 'ANI-PWD'),
    (UUID(), '2-FMA',                    '2-fma',               'Research-grade 2-fluoromethamphetamine. Laboratory use only.',                                            'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 4, 2, 29.99, '2FMA-RC'),
    (UUID(), 'Kratom Extract 50x',       'kratom-extract-50x',  'Ultra-concentrated Kratom extract, 50x potency from premium Borneo leaves.',                             'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 1, 5, 3, 18.99, 'KRA-50X'),
    (UUID(), 'Alpha-GPC 99%',            'alpha-gpc-99',        'Pharmaceutical-grade Alpha-GPC for cognitive enhancement research.',                                      'https://i.ibb.co/KpDhj0rZ/IMG-2197.jpg', 0, 3, 4, 22.99, 'AGPC-99');

-- Product variants (uuid generated inline)
-- CBD Isolate (product 1)
INSERT INTO product_variants (uuid, product_id, weight_label, unit, weight_grams, price, stock, sku) VALUES
    (UUID(), 1, '1g',   '1g',   1,    9.99, 100, 'CBD-ISO-99-1G'),
    (UUID(), 1, '5g',   '5g',   5,   39.99,  80, 'CBD-ISO-99-5G'),
    (UUID(), 1, '10g',  '10g',  10,  69.99,  50, 'CBD-ISO-99-10G'),
    (UUID(), 1, '25g',  '25g',  25, 149.99,  30, 'CBD-ISO-99-25G'),
    (UUID(), 1, '250g', '250g', 250,999.99,  10, 'CBD-ISO-99-250G');

-- CBG Powder (product 2)
INSERT INTO product_variants (uuid, product_id, weight_label, unit, weight_grams, price, stock, sku) VALUES
    (UUID(), 2, '1g',   '1g',   1,    14.99, 100, 'CBG-PWD-1G'),
    (UUID(), 2, '5g',   '5g',   5,    59.99,  60, 'CBG-PWD-5G'),
    (UUID(), 2, '10g',  '10g',  10,  109.99,  40, 'CBG-PWD-10G'),
    (UUID(), 2, '25g',  '25g',  25,  239.99,  20, 'CBG-PWD-25G'),
    (UUID(), 2, '250g', '250g', 250,1799.99,   5, 'CBG-PWD-250G');

-- Psilocybin (product 3)
INSERT INTO product_variants (uuid, product_id, weight_label, unit, weight_grams, price, stock, sku) VALUES
    (UUID(), 3, '1g',   '1g',   1,    24.99,  80, 'PSI-EXT-1G'),
    (UUID(), 3, '5g',   '5g',   5,    99.99,  40, 'PSI-EXT-5G'),
    (UUID(), 3, '10g',  '10g',  10,  179.99,  20, 'PSI-EXT-10G'),
    (UUID(), 3, '25g',  '25g',  25,  399.99,  10, 'PSI-EXT-25G'),
    (UUID(), 3, '250g', '250g', 250,2999.99,   2, 'PSI-EXT-250G');

-- Lion's Mane (product 4)
INSERT INTO product_variants (uuid, product_id, weight_label, unit, weight_grams, price, stock, sku) VALUES
    (UUID(), 4, '1g',   '1g',   1,    19.99, 100, 'LME-30P-1G'),
    (UUID(), 4, '5g',   '5g',   5,    79.99,  70, 'LME-30P-5G'),
    (UUID(), 4, '10g',  '10g',  10,  139.99,  50, 'LME-30P-10G'),
    (UUID(), 4, '25g',  '25g',  25,  299.99,  25, 'LME-30P-25G'),
    (UUID(), 4, '250g', '250g', 250,2199.99,   5, 'LME-30P-250G');

-- Aniracetam (product 5)
INSERT INTO product_variants (uuid, product_id, weight_label, unit, weight_grams, price, stock, sku) VALUES
    (UUID(), 5, '1g',   '1g',   1,    12.99, 120, 'ANI-PWD-1G'),
    (UUID(), 5, '5g',   '5g',   5,    49.99,  80, 'ANI-PWD-5G'),
    (UUID(), 5, '10g',  '10g',  10,   89.99,  60, 'ANI-PWD-10G'),
    (UUID(), 5, '25g',  '25g',  25,  199.99,  30, 'ANI-PWD-25G'),
    (UUID(), 5, '250g', '250g', 250,1499.99,   8, 'ANI-PWD-250G');

-- 2-FMA (product 6)
INSERT INTO product_variants (uuid, product_id, weight_label, unit, weight_grams, price, stock, sku) VALUES
    (UUID(), 6, '1g',   '1g',   1,    29.99,  60, '2FMA-RC-1G'),
    (UUID(), 6, '5g',   '5g',   5,   119.99,  30, '2FMA-RC-5G'),
    (UUID(), 6, '10g',  '10g',  10,  219.99,  15, '2FMA-RC-10G'),
    (UUID(), 6, '25g',  '25g',  25,  499.99,   8, '2FMA-RC-25G'),
    (UUID(), 6, '250g', '250g', 250,3999.99,   2, '2FMA-RC-250G');

-- Kratom (product 7)
INSERT INTO product_variants (uuid, product_id, weight_label, unit, weight_grams, price, stock, sku) VALUES
    (UUID(), 7, '1g',   '1g',   1,    18.99, 100, 'KRA-50X-1G'),
    (UUID(), 7, '5g',   '5g',   5,    74.99,  60, 'KRA-50X-5G'),
    (UUID(), 7, '10g',  '10g',  10,  134.99,  40, 'KRA-50X-10G'),
    (UUID(), 7, '25g',  '25g',  25,  299.99,  20, 'KRA-50X-25G'),
    (UUID(), 7, '250g', '250g', 250,2299.99,   4, 'KRA-50X-250G');

-- Alpha-GPC (product 8)
INSERT INTO product_variants (uuid, product_id, weight_label, unit, weight_grams, price, stock, sku) VALUES
    (UUID(), 8, '1g',   '1g',   1,    22.99, 100, 'AGPC-99-1G'),
    (UUID(), 8, '5g',   '5g',   5,    89.99,  70, 'AGPC-99-5G'),
    (UUID(), 8, '10g',  '10g',  10,  159.99,  50, 'AGPC-99-10G'),
    (UUID(), 8, '25g',  '25g',  25,  349.99,  25, 'AGPC-99-25G'),
    (UUID(), 8, '250g', '250g', 250,2699.99,   5, 'AGPC-99-250G');

-- Default admin user: username=admin, password=changeme (MUST change on first login)
-- Password hash for 'changeme' — generated with password_hash('changeme', PASSWORD_BCRYPT)
INSERT INTO admin_users (username, password_hash) VALUES
    ('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
