-- Seed the 6 product types
INSERT IGNORE INTO products (name, pack_size, form) VALUES
    ('Big Pack Granules',   'big',   'granules'),
    ('Big Pack Tablet',     'big',   'tablet'),
    ('Big Pack Powder',     'big',   'powder'),
    ('Small Pack Granules', 'small', 'granules'),
    ('Small Pack Tablet',   'small', 'tablet'),
    ('Small Pack Powder',   'small', 'powder');
