-- CnrByte Step 19 schema integrity patch.
-- Apply after 01_create_tables.sql and the existing migrations (06 through 11).
-- Back up the database and run this against staging before production.
USE `cnrbyte`;

-- settings/settings.run.php writes a product status when administrators create a product.
-- The original base schema omitted this column, causing product creation to fail on clean installs.
SET @has_products_status = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'status'
);
SET @sql_products_status = IF(
  @has_products_status = 0,
  "ALTER TABLE products ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'available' AFTER avg_profit_margin_rate",
  'SELECT 1'
);
PREPARE stmt_products_status FROM @sql_products_status;
EXECUTE stmt_products_status;
DEALLOCATE PREPARE stmt_products_status;

-- Older installations may have a narrower ENUM status column. Normalize it to
-- VARCHAR so values used by the admin product form (for example, Ready for Pickup)
-- do not fail at insert time.
ALTER TABLE products MODIFY COLUMN status VARCHAR(40) NULL DEFAULT 'available';
UPDATE products SET status = 'available' WHERE status IS NULL OR TRIM(status) = '';
ALTER TABLE products MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'available';

-- Helpful indexes for user-owned quotation history and quote line lookup.
SET @has_quotations_client_index = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotations' AND INDEX_NAME = 'idx_quotations_client_created'
);
SET @sql_quotations_client_index = IF(
  @has_quotations_client_index = 0,
  'CREATE INDEX idx_quotations_client_created ON quotations (client_id, created_at)',
  'SELECT 1'
);
PREPARE stmt_quotations_client_index FROM @sql_quotations_client_index;
EXECUTE stmt_quotations_client_index;
DEALLOCATE PREPARE stmt_quotations_client_index;

SET @has_quotation_items_quote_index = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotation_items' AND INDEX_NAME = 'idx_quotation_items_quote'
);
SET @sql_quotation_items_quote_index = IF(
  @has_quotation_items_quote_index = 0,
  'CREATE INDEX idx_quotation_items_quote ON quotation_items (quote_id, product_id)',
  'SELECT 1'
);
PREPARE stmt_quotation_items_quote_index FROM @sql_quotation_items_quote_index;
EXECUTE stmt_quotation_items_quote_index;
DEALLOCATE PREPARE stmt_quotation_items_quote_index;

-- Composite indexes used by branch-scoped duplicate checks in statement workflows.
SET @has_crj_statement_index = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crj' AND INDEX_NAME = 'idx_crj_branch_account_amount_date'
);
SET @sql_crj_statement_index = IF(
  @has_crj_statement_index = 0,
  'CREATE INDEX idx_crj_branch_account_amount_date ON crj (vendor_id, branch_id, acc_id, bank, trans_date)',
  'SELECT 1'
);
PREPARE stmt_crj_statement_index FROM @sql_crj_statement_index;
EXECUTE stmt_crj_statement_index;
DEALLOCATE PREPARE stmt_crj_statement_index;

SET @has_cpj_statement_index = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cpj' AND INDEX_NAME = 'idx_cpj_branch_account_amount_date'
);
SET @sql_cpj_statement_index = IF(
  @has_cpj_statement_index = 0,
  'CREATE INDEX idx_cpj_branch_account_amount_date ON cpj (vendor_id, branch_id, acc_id, bank, trans_date)',
  'SELECT 1'
);
PREPARE stmt_cpj_statement_index FROM @sql_cpj_statement_index;
EXECUTE stmt_cpj_statement_index;
DEALLOCATE PREPARE stmt_cpj_statement_index;

SET @has_balance_statement_index = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bal_stmt' AND INDEX_NAME = 'idx_bal_stmt_branch_account_cost_date'
);
SET @sql_balance_statement_index = IF(
  @has_balance_statement_index = 0,
  'CREATE INDEX idx_bal_stmt_branch_account_cost_date ON bal_stmt (vendor_id, branch_id, acc_id, cost, date_obtained)',
  'SELECT 1'
);
PREPARE stmt_balance_statement_index FROM @sql_balance_statement_index;
EXECUTE stmt_balance_statement_index;
DEALLOCATE PREPARE stmt_balance_statement_index;

SET @has_shares_statement_index = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shares' AND INDEX_NAME = 'idx_shares_branch_account_type_date'
);
SET @sql_shares_statement_index = IF(
  @has_shares_statement_index = 0,
  'CREATE INDEX idx_shares_branch_account_type_date ON shares (vendor_id, branch_id, acc_id, share_type, issue_date)',
  'SELECT 1'
);
PREPARE stmt_shares_statement_index FROM @sql_shares_statement_index;
EXECUTE stmt_shares_statement_index;
DEALLOCATE PREPARE stmt_shares_statement_index;

SET @has_types_owner_lookup_index = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'types' AND INDEX_NAME = 'idx_types_owner_type_name'
);
SET @sql_types_owner_lookup_index = IF(
  @has_types_owner_lookup_index = 0,
  'CREATE INDEX idx_types_owner_type_name ON types (client_id, type, type_name)',
  'SELECT 1'
);
PREPARE stmt_types_owner_lookup_index FROM @sql_types_owner_lookup_index;
EXECUTE stmt_types_owner_lookup_index;
DEALLOCATE PREPARE stmt_types_owner_lookup_index;
