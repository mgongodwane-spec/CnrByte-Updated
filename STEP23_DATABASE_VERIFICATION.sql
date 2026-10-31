-- CnrByte Step 23: READ-ONLY staging database verification.
-- BACK UP the database first. Run against a staging copy, not production.
-- Select the target database before running this file (USE `cnrbyte` if appropriate).
-- This script does not alter, delete, merge, or insert data.
-- Prerequisite: the project's baseline and applicable step 13/19/20/22 schema migrations
-- have been applied. Run section 1 first; if required tables/columns are missing,
-- stop and apply/review the relevant migration before running later sections.

-- 1. Schema inventory for the main workflows. Missing expected columns will simply
-- be absent from this result and can be compared against the requested column list.
SELECT expected.table_name, expected.column_name,
       actual.COLUMN_TYPE, actual.IS_NULLABLE, actual.COLUMN_DEFAULT,
       CASE WHEN actual.COLUMN_NAME IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS schema_status
FROM (
    SELECT 'types' AS table_name, 'id' AS column_name UNION ALL
    SELECT 'types','client_id' UNION ALL SELECT 'types','type' UNION ALL SELECT 'types','type_name' UNION ALL
    SELECT 'ranks','client_id' UNION ALL SELECT 'ranks','vendor_id' UNION ALL SELECT 'ranks','rank_name' UNION ALL SELECT 'ranks','qualification_rank' UNION ALL SELECT 'ranks','occupation' UNION ALL
    SELECT 'staff','vendor_id' UNION ALL SELECT 'staff','branch_id' UNION ALL SELECT 'staff','occupation' UNION ALL SELECT 'staff','contract' UNION ALL
    SELECT 'qualifications','staff_id' UNION ALL SELECT 'qualifications','qualification' UNION ALL SELECT 'qualifications','qualification_field' UNION ALL
    SELECT 'staff_leave','vendor_id' UNION ALL SELECT 'staff_leave','staff_id' UNION ALL SELECT 'staff_leave','leave_type' UNION ALL SELECT 'staff_leave','leave_status' UNION ALL
    SELECT 'staff_earnings_deductions','vendor_id' UNION ALL SELECT 'staff_earnings_deductions','staff_id' UNION ALL SELECT 'staff_earnings_deductions','entry_type' UNION ALL SELECT 'staff_earnings_deductions','description' UNION ALL SELECT 'staff_earnings_deductions','amount' UNION ALL
    SELECT 'products','vendor_id' UNION ALL SELECT 'products','great' UNION ALL SELECT 'products','grand' UNION ALL SELECT 'products','parent' UNION ALL SELECT 'products','child' UNION ALL
    SELECT 'product_great_categories','id' UNION ALL SELECT 'product_great_categories','category' UNION ALL
    SELECT 'product_grand_categories','id' UNION ALL SELECT 'product_grand_categories','great_id' UNION ALL SELECT 'product_grand_categories','category' UNION ALL
    SELECT 'product_parent_categories','id' UNION ALL SELECT 'product_parent_categories','great_id' UNION ALL SELECT 'product_parent_categories','grand_id' UNION ALL SELECT 'product_parent_categories','category' UNION ALL
    SELECT 'product_child_categories','id' UNION ALL SELECT 'product_child_categories','great_id' UNION ALL SELECT 'product_child_categories','grand_id' UNION ALL SELECT 'product_child_categories','parent_id' UNION ALL SELECT 'product_child_categories','category' UNION ALL
    SELECT 'cnrbyte_messages','sender_type' UNION ALL SELECT 'cnrbyte_messages','sender_id' UNION ALL SELECT 'cnrbyte_messages','recipient_type' UNION ALL SELECT 'cnrbyte_messages','recipient_id' UNION ALL
    SELECT 'cnrbyte_notifications','recipient_type' UNION ALL SELECT 'cnrbyte_notifications','recipient_id' UNION ALL SELECT 'cnrbyte_notifications','is_read'
) AS expected
LEFT JOIN INFORMATION_SCHEMA.COLUMNS actual
  ON actual.TABLE_SCHEMA = DATABASE()
 AND actual.TABLE_NAME = expected.table_name
 AND actual.COLUMN_NAME = expected.column_name
ORDER BY expected.table_name, expected.column_name;

-- 2. Duplicate lookup values by owner/type/name. Review before considering a unique index.
SELECT client_id, type, LOWER(TRIM(type_name)) AS normalized_type_name,
       COUNT(*) AS duplicate_count, GROUP_CONCAT(id ORDER BY id) AS row_ids,
       GROUP_CONCAT(type_name ORDER BY id SEPARATOR ' | ') AS stored_names
FROM types
GROUP BY client_id, type, LOWER(TRIM(type_name))
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, client_id, type;

-- 3. Lookup rows that are blank or have leading/trailing spaces.
SELECT id, client_id, type, type_name
FROM types
WHERE type_name IS NULL OR TRIM(type_name) = '' OR type_name <> TRIM(type_name)
ORDER BY client_id, type, id;

-- 4. Duplicate rank definitions under the same client/vendor scope.
SELECT client_id, vendor_id, LOWER(TRIM(rank_name)) AS normalized_rank_name,
       LOWER(TRIM(occupation)) AS normalized_occupation, COUNT(*) AS duplicate_count,
       GROUP_CONCAT(id ORDER BY id) AS row_ids
FROM ranks
GROUP BY client_id, vendor_id, LOWER(TRIM(rank_name)), LOWER(TRIM(occupation))
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, vendor_id;

-- 5. Product category duplicate names and invalid hierarchy ancestry.
SELECT LOWER(TRIM(category)) AS normalized_name, COUNT(*) AS row_count,
       GROUP_CONCAT(id ORDER BY id) AS row_ids
FROM product_great_categories
GROUP BY LOWER(TRIM(category)) HAVING COUNT(*) > 1;

SELECT great_id, LOWER(TRIM(category)) AS normalized_name, COUNT(*) AS row_count,
       GROUP_CONCAT(id ORDER BY id) AS row_ids
FROM product_grand_categories
GROUP BY great_id, LOWER(TRIM(category)) HAVING COUNT(*) > 1;

SELECT p.id, p.great_id, p.grand_id
FROM product_parent_categories p
LEFT JOIN product_grand_categories g ON g.id = p.grand_id AND g.great_id = p.great_id
WHERE g.id IS NULL;

SELECT c.id, c.parent_id, c.grand_id, c.great_id
FROM product_child_categories c
LEFT JOIN product_parent_categories p
  ON p.id = c.parent_id AND p.grand_id = c.grand_id AND p.great_id = c.great_id
WHERE p.id IS NULL;

SELECT pr.id AS product_id, pr.product_name, pr.great, pr.grand, pr.parent, pr.child
FROM products pr
LEFT JOIN product_child_categories c ON c.id = pr.child
LEFT JOIN product_parent_categories p
  ON p.id = c.parent_id AND p.id = pr.parent AND p.grand_id = pr.grand AND p.great_id = pr.great
LEFT JOIN product_grand_categories g ON g.id = p.grand_id AND g.id = pr.grand AND g.great_id = pr.great
LEFT JOIN product_great_categories gg ON gg.id = g.great_id AND gg.id = pr.great
WHERE c.id IS NULL OR p.id IS NULL OR g.id IS NULL OR gg.id IS NULL;

-- 6. Staff/HR orphan and vendor-scope checks.
SELECT q.id AS qualification_id, q.staff_id
FROM qualifications q
LEFT JOIN staff s ON s.id = q.staff_id
WHERE s.id IS NULL;

SELECT l.id AS leave_id, l.vendor_id AS leave_vendor_id, l.staff_id,
       s.vendor_id AS staff_vendor_id
FROM staff_leave l
LEFT JOIN staff s ON s.id = l.staff_id
WHERE s.id IS NULL OR s.vendor_id <> l.vendor_id;

SELECT e.id AS payroll_item_id, e.vendor_id AS item_vendor_id, e.staff_id,
       s.vendor_id AS staff_vendor_id
FROM staff_earnings_deductions e
LEFT JOIN staff s ON s.id = e.staff_id
WHERE s.id IS NULL OR s.vendor_id <> e.vendor_id;

-- 7. Review owner/type coverage for Settings-backed form values.
SELECT type, client_id AS owner_id, COUNT(*) AS value_count,
       SUM(type_name IS NULL OR TRIM(type_name) = '') AS blank_value_count
FROM types
WHERE type IN ('occupation_type','contract_type','qualification_type','leave_type','earnings_type','deductions_type')
GROUP BY type, client_id
ORDER BY type, client_id;

-- 8. Confirm relevant indexes before proposing schema changes; this is read-only.
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE,
       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('types','ranks','products','product_great_categories',
                     'product_grand_categories','product_parent_categories','product_child_categories',
                     'staff','qualifications','staff_leave','staff_earnings_deductions')
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
ORDER BY TABLE_NAME, INDEX_NAME;
