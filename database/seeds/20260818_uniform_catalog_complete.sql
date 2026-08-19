/*
 * Complete the local uniform-store fixture.
 *
 * This is deliberately repeatable and only targets the test uniform items
 * (inventory item IDs 11-25).  Images are stored separately under
 * uploads/uniform_catalog/<catalog_product_id>/uniform.jpg.
 */

START TRANSACTION;

INSERT INTO suppliers (name, contact_person, phone, email, address, status)
SELECT 'Kingsway Uniforms Test Supplier', 'Test Supplier Desk', '0712345678',
       'uniforms.test@supplier.example', 'Nairobi, Kenya', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM suppliers WHERE name = 'Kingsway Uniforms Test Supplier'
);

UPDATE inventory_items
SET supplier_id = (SELECT id FROM suppliers WHERE name = 'Kingsway Uniforms Test Supplier' LIMIT 1),
    barcode = CASE id
        WHEN 11 THEN 'KPS-UNF-00011'
        WHEN 12 THEN 'KPS-UNF-00012'
        WHEN 13 THEN 'KPS-UNF-00013'
        WHEN 14 THEN 'KPS-UNF-00014'
        WHEN 15 THEN 'KPS-UNF-00015'
        WHEN 16 THEN 'KPS-UNF-00016'
        WHEN 17 THEN 'KPS-UNF-00017'
        WHEN 18 THEN 'KPS-UNF-00018'
        WHEN 19 THEN 'KPS-UNF-00019'
        WHEN 20 THEN 'KPS-UNF-00020'
        WHEN 21 THEN 'KPS-UNF-00021'
        WHEN 22 THEN 'KPS-UNF-00022'
        WHEN 23 THEN 'KPS-UNF-00023'
        WHEN 24 THEN 'KPS-UNF-00024'
        WHEN 25 THEN 'KPS-UNF-00025'
    END,
    sku = CASE id
        WHEN 11 THEN 'SKU-KPS-SWEATER'
        WHEN 12 THEN 'SKU-KPS-SOCK-PACK'
        WHEN 13 THEN 'SKU-KPS-SHORTS'
        WHEN 14 THEN 'SKU-KPS-TROUSERS'
        WHEN 15 THEN 'SKU-KPS-SHIRT-BOYS'
        WHEN 16 THEN 'SKU-KPS-BLOUSE-GIRLS'
        WHEN 17 THEN 'SKU-KPS-SKIRT-GIRLS'
        WHEN 18 THEN 'SKU-KPS-GAMES-SKIRT'
        WHEN 19 THEN 'SKU-KPS-BOARDING-PJ'
        WHEN 20 THEN 'SKU-KPS-TRACKSUIT'
        WHEN 21 THEN 'SKU-KPS-SOCK-PAIR'
        WHEN 22 THEN 'SKU-KPS-GAMES-TEE'
        WHEN 23 THEN 'SKU-KPS-TIE'
        WHEN 24 THEN 'SKU-KPS-BELT'
        WHEN 25 THEN 'SKU-KPS-CAP'
    END
WHERE id BETWEEN 11 AND 25;

UPDATE inventory_items
SET
    description = CASE id
        WHEN 11 THEN 'Grey Kingsway Preparatory school sweater for cool mornings and classroom wear.'
        WHEN 12 THEN 'White cotton school socks, supplied as a practical three-pair pack.'
        WHEN 13 THEN 'Navy blue school shorts for daily summer and sports-day wear.'
        WHEN 14 THEN 'Navy blue school trousers with a comfortable straight-leg fit for school wear.'
        WHEN 15 THEN 'White short-sleeve school shirt for boys, finished with the Kingsway school uniform standard.'
        WHEN 16 THEN 'White school blouse for girls, designed for comfortable daily classroom wear.'
        WHEN 17 THEN 'Navy blue school skirt for girls with a durable pleated finish.'
        WHEN 18 THEN 'Navy games skirt for physical education and school sports activities.'
        WHEN 19 THEN 'Warm sleeping-pajama set for full boarders staying at school.'
        WHEN 20 THEN 'Navy and green Kingsway tracksuit for sports, travel and cold-weather activities.'
        WHEN 21 THEN 'White cotton school socks supplied as individual pairs for everyday uniform wear.'
        WHEN 22 THEN 'Kingsway games T-shirt for physical education and sports activities.'
        WHEN 23 THEN 'School tie in the official Kingsway uniform colours.'
        WHEN 24 THEN 'Black school belt with a plain durable buckle for daily uniform wear.'
        WHEN 25 THEN 'Kingsway school cap or sun hat for outdoor activities and sports.'
    END,
    brand = 'Kingsway Preparatory School',
    model = CASE id
        WHEN 11 THEN 'KPS-SWEATER'
        WHEN 12 THEN 'KPS-SOCK-PACK-3'
        WHEN 13 THEN 'KPS-SHORTS'
        WHEN 14 THEN 'KPS-TROUSERS'
        WHEN 15 THEN 'KPS-SHIRT-BOYS'
        WHEN 16 THEN 'KPS-BLOUSE-GIRLS'
        WHEN 17 THEN 'KPS-SKIRT-GIRLS'
        WHEN 18 THEN 'KPS-GAMES-SKIRT'
        WHEN 19 THEN 'KPS-BOARDING-PJ'
        WHEN 20 THEN 'KPS-TRACKSUIT'
        WHEN 21 THEN 'KPS-SOCK-PAIR'
        WHEN 22 THEN 'KPS-GAMES-TEE'
        WHEN 23 THEN 'KPS-TIE'
        WHEN 24 THEN 'KPS-BELT'
        WHEN 25 THEN 'KPS-CAP'
    END,
    location = 'Uniform Store',
    status = 'active',
    updated_at = CURRENT_TIMESTAMP
WHERE id BETWEEN 11 AND 25;

/* Give the six newer items realistic stock in every defined size. */
UPDATE uniform_sizes
SET quantity_available = CASE item_id
        WHEN 20 THEN 24
        WHEN 21 THEN 36
        WHEN 22 THEN 30
        WHEN 23 THEN 40
        WHEN 24 THEN 30
        WHEN 25 THEN 24
    END,
    quantity_reserved = 0,
    quantity_sold = 0,
    reorder_level = 8,
    last_restocked = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
WHERE item_id BETWEEN 20 AND 25;

UPDATE uniform_sizes
SET size_label = CASE
        WHEN size_type = 'clothing' THEN CONCAT('Clothing size ', size)
        WHEN size_type = 'shoe' THEN CONCAT('Shoe size ', size)
        WHEN size_type = 'waist' THEN CONCAT('Waist ', size, ' inches')
        WHEN size_type = 'one_size' THEN 'One size fits most'
        ELSE size
    END,
    last_restocked = COALESCE(last_restocked, CURRENT_TIMESTAMP),
    updated_at = CURRENT_TIMESTAMP
WHERE item_id BETWEEN 11 AND 25;

/* Existing fixtures must also be orderable in every defined size. */
UPDATE uniform_sizes
SET quantity_available = 20,
    quantity_reserved = 0,
    quantity_sold = 0,
    reorder_level = 8,
    last_restocked = COALESCE(last_restocked, CURRENT_TIMESTAMP),
    updated_at = CURRENT_TIMESTAMP
WHERE item_id BETWEEN 11 AND 25
  AND quantity_available = 0;

UPDATE inventory_items i
JOIN (
    SELECT item_id, SUM(quantity_available) AS stock
    FROM uniform_sizes
    WHERE item_id BETWEEN 11 AND 25
    GROUP BY item_id
) s ON s.item_id = i.id
SET i.current_quantity = s.stock,
    i.last_purchase_date = CURRENT_DATE,
    i.last_purchase_price = i.unit_cost,
    i.last_audit_date = CURRENT_DATE,
    i.updated_at = CURRENT_TIMESTAMP;

INSERT INTO uniform_catalog_products
    (item_id, slug, title, description, published, status, created_by)
VALUES
    (11, 'school-sweater', 'School Sweater', 'Grey Kingsway Preparatory school sweater for cool mornings and classroom wear.', 1, 'active', 10),
    (12, 'school-socks-pack-of-three', 'School Socks — Pack of 3', 'White cotton school socks supplied as a practical three-pair pack.', 1, 'active', 10),
    (13, 'school-shorts', 'School Shorts', 'Navy blue school shorts for daily summer and sports-day wear.', 1, 'active', 10),
    (14, 'school-trousers', 'School Trousers', 'Navy blue school trousers with a comfortable straight-leg fit for school wear.', 1, 'active', 10),
    (15, 'school-shirt-boys', 'School Shirt — Boys', 'White short-sleeve school shirt for boys, finished with the Kingsway school uniform standard.', 1, 'active', 10),
    (16, 'school-blouse-girls', 'School Blouse — Girls', 'White school blouse for girls, designed for comfortable daily classroom wear.', 1, 'active', 10),
    (17, 'school-skirt-girls', 'School Skirt — Girls', 'Navy blue school skirt for girls with a durable pleated finish.', 1, 'active', 10),
    (18, 'games-skirt', 'Games Skirt', 'Navy games skirt for physical education and school sports activities.', 1, 'active', 10),
    (19, 'sleeping-pajamas', 'Sleeping Pajamas', 'Warm sleeping-pajama set for full boarders staying at school.', 1, 'active', 10),
    (20, 'school-track-suit', 'School Track Suit', 'Navy and green Kingsway tracksuit for sports, travel and cold-weather activities.', 1, 'active', 10),
    (21, 'school-socks', 'School Socks', 'White cotton school socks supplied as individual pairs for everyday uniform wear.', 1, 'active', 10),
    (22, 'games-t-shirt', 'Games T-Shirt', 'Kingsway games T-shirt for physical education and sports activities.', 1, 'active', 10),
    (23, 'school-tie', 'School Tie', 'School tie in the official Kingsway uniform colours.', 1, 'active', 10),
    (24, 'school-belt', 'School Belt', 'Black school belt with a plain durable buckle for daily uniform wear.', 1, 'active', 10),
    (25, 'school-cap-hat', 'School Cap / Sun Hat', 'Kingsway school cap or sun hat for outdoor activities and sports.', 1, 'active', 10)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    published = VALUES(published),
    status = VALUES(status),
    updated_at = CURRENT_TIMESTAMP;

/* Do not leave a published product without a primary local image. */
UPDATE uniform_catalog_images i
JOIN uniform_catalog_products p ON p.id = i.product_id
SET i.url = CONCAT('/Kingsway/uploads/uniform_catalog/', p.id, '/uniform.jpg'),
    i.alt_text = CONCAT(p.title, ' — Kingsway Preparatory School')
WHERE p.item_id BETWEEN 11 AND 25
  AND i.is_primary = 1;

INSERT INTO uniform_catalog_images (product_id, url, alt_text, is_primary)
SELECT p.id,
       CONCAT('/Kingsway/uploads/uniform_catalog/', p.id, '/uniform.jpg'),
       CONCAT(p.title, ' — Kingsway Preparatory School'),
       1
FROM uniform_catalog_products p
LEFT JOIN uniform_catalog_images i
    ON i.product_id = p.id AND i.is_primary = 1
WHERE p.item_id BETWEEN 11 AND 25
  AND i.id IS NULL;

COMMIT;
