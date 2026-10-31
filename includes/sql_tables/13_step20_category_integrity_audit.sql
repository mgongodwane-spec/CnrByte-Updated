-- CnrByte Step 20: read-only product-category integrity audit.
-- Run against a BACKUP or staging database first. These queries report issues only;
-- they do not delete, merge, or rewrite existing category/product data.

-- 1. Duplicate great categories (case/space behaviour depends on DB collation).
SELECT LOWER(TRIM(category)) AS normalized_name, COUNT(*) AS row_count,
       GROUP_CONCAT(id ORDER BY id) AS category_ids
FROM product_great_categories
GROUP BY LOWER(TRIM(category)) HAVING COUNT(*) > 1;

-- 2. Duplicate grand names under the same great category.
SELECT great_id, LOWER(TRIM(category)) AS normalized_name, COUNT(*) AS row_count,
       GROUP_CONCAT(id ORDER BY id) AS category_ids
FROM product_grand_categories
GROUP BY great_id, LOWER(TRIM(category)) HAVING COUNT(*) > 1;

-- 3. Parent rows whose grand category does not belong to their great category.
SELECT p.id, p.great_id, p.grand_id
FROM product_parent_categories p
LEFT JOIN product_grand_categories g ON g.id = p.grand_id AND g.great_id = p.great_id
WHERE g.id IS NULL;

-- 4. Child rows whose parent/grand/great ancestry is inconsistent.
SELECT c.id, c.parent_id, c.grand_id, c.great_id
FROM product_child_categories c
LEFT JOIN product_parent_categories p
  ON p.id = c.parent_id AND p.grand_id = c.grand_id AND p.great_id = c.great_id
WHERE p.id IS NULL;

-- 5. Products without a complete, consistent four-level path.
SELECT pr.id AS product_id, pr.product_name, pr.great, pr.grand, pr.parent, pr.child
FROM products pr
LEFT JOIN product_child_categories c ON c.id = pr.child
LEFT JOIN product_parent_categories p
  ON p.id = c.parent_id AND p.id = pr.parent AND p.grand_id = pr.grand AND p.great_id = pr.great
LEFT JOIN product_grand_categories g ON g.id = p.grand_id AND g.id = pr.grand AND g.great_id = pr.great
LEFT JOIN product_great_categories gg ON gg.id = g.great_id AND gg.id = pr.great
WHERE c.id IS NULL OR p.id IS NULL OR g.id IS NULL OR gg.id IS NULL;

-- 6. Product counts by leaf category, including categories with no products.
SELECT c.id AS child_id, c.category AS child_category,
       p.category AS parent_category, g.category AS grand_category, gg.category AS great_category,
       COUNT(pr.id) AS product_count
FROM product_child_categories c
JOIN product_parent_categories p ON p.id = c.parent_id AND p.grand_id = c.grand_id AND p.great_id = c.great_id
JOIN product_grand_categories g ON g.id = p.grand_id AND g.great_id = p.great_id
JOIN product_great_categories gg ON gg.id = g.great_id
LEFT JOIN products pr ON pr.child = c.id AND pr.parent = p.id AND pr.grand = g.id AND pr.great = gg.id
GROUP BY c.id, c.category, p.category, g.category, gg.category
ORDER BY gg.category, g.category, p.category, c.category;

-- After reviewing and resolving all duplicate results, consider these UNIQUE indexes.
-- Do not uncomment blindly: existing duplicate records will cause index creation to fail.
-- ALTER TABLE product_great_categories ADD UNIQUE KEY uq_product_great_category (category);
-- ALTER TABLE product_grand_categories ADD UNIQUE KEY uq_product_grand_category (great_id, category);
-- ALTER TABLE product_parent_categories ADD UNIQUE KEY uq_product_parent_category (grand_id, great_id, category);
-- ALTER TABLE product_child_categories ADD UNIQUE KEY uq_product_child_category (parent_id, grand_id, great_id, category);
