-- CnrByte Steps 9-10: Inventory audit trail and double-entry general ledger foundation
-- Runtime bootstrap in marketplace/marketplace_orders.php creates these idempotently before checkout.
-- Run this migration on staging first. Existing products receive units=0; set opening stock deliberately.
-- If products.units does not exist yet, add it before using stock-controlled checkout:
-- ALTER TABLE products ADD COLUMN units DECIMAL(12,2) NOT NULL DEFAULT 0.00;
-- The runtime bootstrap checks for this column and adds it when missing.

CREATE TABLE IF NOT EXISTS inventory_movements (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL, product_id INT NOT NULL, order_id INT DEFAULT NULL,
  movement_type VARCHAR(32) NOT NULL, quantity_delta DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00, value_delta DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  reference VARCHAR(100) DEFAULT NULL, created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inventory_order_product_type (order_id, product_id, movement_type),
  INDEX (vendor_id, product_id), INDEX (created_at), INDEX (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chart_of_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY, vendor_id INT NOT NULL, account_code VARCHAR(20) NOT NULL,
  account_name VARCHAR(120) NOT NULL, account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 1, is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_vendor_account_code (vendor_id, account_code), INDEX (vendor_id, account_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_entries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, vendor_id INT NOT NULL, source_type VARCHAR(40) NOT NULL, source_id BIGINT NOT NULL,
  reference VARCHAR(100) NOT NULL, entry_date DATE NOT NULL, description VARCHAR(255) NOT NULL,
  status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'posted', created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_journal_source (vendor_id, source_type, source_id),
  INDEX (vendor_id, entry_date), INDEX (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_entry_lines (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, journal_entry_id BIGINT NOT NULL, account_id INT NOT NULL,
  debit DECIMAL(14,2) NOT NULL DEFAULT 0.00, credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  description VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (journal_entry_id), INDEX (account_id),
  CONSTRAINT fk_journal_lines_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_journal_lines_account FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
