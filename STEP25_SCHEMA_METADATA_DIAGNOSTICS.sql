-- CnrByte Step 25: read-only schema metadata diagnostics.
-- Run against a BACKUP/STAGING database first. This script does not modify data or schema.
-- It reports tables, columns, indexes and foreign keys for comparison with application code.

SELECT DATABASE() AS current_database, VERSION() AS database_version;

-- Tables and estimated row counts
SELECT TABLE_NAME, ENGINE, TABLE_ROWS, TABLE_COLLATION
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- Columns, types, nullability, defaults and generated-column metadata
SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
       COLUMN_DEFAULT, EXTRA, GENERATION_EXPRESSION
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Index definitions, including uniqueness
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME,
       SUB_PART, INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Foreign-key definitions
SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME,
       REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;

-- Candidate schema objects used by this project. Missing results should be compared with
-- the current code and migration history before deciding whether a migration is required.
SELECT expected.TABLE_NAME,
       CASE WHEN actual.TABLE_NAME IS NULL THEN 'MISSING TABLE' ELSE 'PRESENT' END AS audit_status
FROM (
  SELECT 'clients' AS TABLE_NAME UNION ALL SELECT 'types' UNION ALL SELECT 'vendors'
  UNION ALL SELECT 'branches' UNION ALL SELECT 'shifts' UNION ALL SELECT 'staff'
  UNION ALL SELECT 'profiles' UNION ALL SELECT 'ranks' UNION ALL SELECT 'products'
  UNION ALL SELECT 'product_great_categories' UNION ALL SELECT 'product_grand_categories'
  UNION ALL SELECT 'product_parent_categories' UNION ALL SELECT 'product_child_categories'
  UNION ALL SELECT 'marketplace_orders' UNION ALL SELECT 'marketplace_order_items'
  UNION ALL SELECT 'marketplace_refunds' UNION ALL SELECT 'marketplace_vendor_settlements'
  UNION ALL SELECT 'cnrbyte_auth_attempts' UNION ALL SELECT 'cnrbyte_password_reset_tokens'
  UNION ALL SELECT 'cnrbyte_messages' UNION ALL SELECT 'cnrbyte_notifications'
  UNION ALL SELECT 'qualifications' UNION ALL SELECT 'staff_leave'
  UNION ALL SELECT 'staff_earnings_deductions' UNION ALL SELECT 'chart_of_accounts'
  UNION ALL SELECT 'journal_entries' UNION ALL SELECT 'journal_entry_lines'
  UNION ALL SELECT 'inventory_movements'
) AS expected
LEFT JOIN INFORMATION_SCHEMA.TABLES actual
  ON actual.TABLE_SCHEMA = DATABASE()
 AND actual.TABLE_NAME = expected.TABLE_NAME
ORDER BY expected.TABLE_NAME;

-- Potential duplicate normalized client emails. Run only if clients.email exists.
-- This query is read-only; resolve conflicts before applying a unique normalized-email key.
SELECT LOWER(TRIM(email)) AS normalized_email, COUNT(*) AS row_count,
       GROUP_CONCAT(id ORDER BY id) AS client_ids
FROM clients
WHERE email IS NOT NULL AND TRIM(email) <> ''
GROUP BY LOWER(TRIM(email))
HAVING COUNT(*) > 1
ORDER BY row_count DESC, normalized_email;

-- Settings lookup duplicates. Review the exact type/owner scope before adding constraints.
SELECT type, client_id, LOWER(TRIM(type_name)) AS normalized_type_name,
       COUNT(*) AS row_count, GROUP_CONCAT(id ORDER BY id) AS type_ids
FROM types
GROUP BY type, client_id, LOWER(TRIM(type_name))
HAVING COUNT(*) > 1
ORDER BY type, client_id, row_count DESC;

-- Product hierarchy duplicates. These checks are safe to run before deciding on unique keys.
SELECT category, COUNT(*) AS row_count, GROUP_CONCAT(id ORDER BY id) AS ids
FROM product_great_categories
GROUP BY category HAVING COUNT(*) > 1;

SELECT great_id, category, COUNT(*) AS row_count, GROUP_CONCAT(id ORDER BY id) AS ids
FROM product_grand_categories
GROUP BY great_id, category HAVING COUNT(*) > 1;

SELECT great_id, grand_id, category, COUNT(*) AS row_count, GROUP_CONCAT(id ORDER BY id) AS ids
FROM product_parent_categories
GROUP BY great_id, grand_id, category HAVING COUNT(*) > 1;

SELECT great_id, grand_id, parent_id, category, COUNT(*) AS row_count,
       GROUP_CONCAT(id ORDER BY id) AS ids
FROM product_child_categories
GROUP BY great_id, grand_id, parent_id, category HAVING COUNT(*) > 1;

-- NOTE: this file intentionally does not ALTER, INSERT, UPDATE or DELETE anything.
