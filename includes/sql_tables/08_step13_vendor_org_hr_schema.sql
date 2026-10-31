-- CnrByte Step 13: missing vendor/org/HR schema alignment.
-- Back up the database and run includes/sql_tables/01_create_tables.sql first.
-- Apply this migration to a staging database before production.
USE `cnrbyte`;

CREATE TABLE IF NOT EXISTS vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  market VARCHAR(120) NOT NULL DEFAULT 'local',
  reg_cert VARCHAR(255) DEFAULT NULL,
  reg_no VARCHAR(120) DEFAULT NULL,
  reg_name VARCHAR(180) NOT NULL,
  reg_date DATE DEFAULT NULL,
  starting_date DATE DEFAULT NULL,
  business_type VARCHAR(120) DEFAULT NULL,
  business_form VARCHAR(120) DEFAULT NULL,
  operation_status VARCHAR(120) DEFAULT NULL,
  fin_year_end DATE DEFAULT NULL,
  tax_no VARCHAR(120) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vendors_client (client_id),
  INDEX idx_vendors_user (user_id),
  INDEX idx_vendors_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade existing vendor tables created by older PHP bootstraps.
SET @has_user_id = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vendors' AND COLUMN_NAME='user_id');
SET @sql_user_id = IF(@has_user_id=0,'ALTER TABLE vendors ADD COLUMN user_id INT DEFAULT NULL AFTER client_id','SELECT 1');
PREPARE stmt_user_id FROM @sql_user_id; EXECUTE stmt_user_id; DEALLOCATE PREPARE stmt_user_id;
SET @has_status = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vendors' AND COLUMN_NAME='status');
SET @sql_status = IF(@has_status=0,"ALTER TABLE vendors ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'",'SELECT 1');
PREPARE stmt_status FROM @sql_status; EXECUTE stmt_status; DEALLOCATE PREPARE stmt_status;
SET @has_verification_status = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vendors' AND COLUMN_NAME='verification_status');
SET @sql_verification_status = IF(@has_verification_status=0,"ALTER TABLE vendors ADD COLUMN verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending'",'SELECT 1');
PREPARE stmt_verification_status FROM @sql_verification_status; EXECUTE stmt_verification_status; DEALLOCATE PREPARE stmt_verification_status;
SET @has_is_active = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vendors' AND COLUMN_NAME='is_active');
SET @sql_is_active = IF(@has_is_active=0,'ALTER TABLE vendors ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1','SELECT 1');
PREPARE stmt_is_active FROM @sql_is_active; EXECUTE stmt_is_active; DEALLOCATE PREPARE stmt_is_active;
SET @has_updated_at = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vendors' AND COLUMN_NAME='updated_at');
SET @sql_updated_at = IF(@has_updated_at=0,'ALTER TABLE vendors ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP','SELECT 1');
PREPARE stmt_updated_at FROM @sql_updated_at; EXECUTE stmt_updated_at; DEALLOCATE PREPARE stmt_updated_at;
UPDATE vendors SET user_id = client_id WHERE user_id IS NULL;

-- Legacy lookup tables still queried by existing PHP modules.
CREATE TABLE IF NOT EXISTS business_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100) NOT NULL,
  type_name VARCHAR(180) NOT NULL,
  client_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_business_types_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendor_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100) NOT NULL,
  type_name VARCHAR(180) NOT NULL,
  client_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vendor_types_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organogram_ranks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  rank_name VARCHAR(150) NOT NULL,
  occupation VARCHAR(150) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_organogram_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS income_expense_list (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(80) NOT NULL,
  type_name VARCHAR(180) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_income_expense_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ppe_depreciation (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  bal_stmt_id INT NOT NULL,
  dep_date DATE NOT NULL,
  dep_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ppe_dep_asset_date (bal_stmt_id, dep_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Legacy private-company and cart tables remain for older checkout paths.
-- New vendor listings should use vendors/products and the marketplace order flow.
CREATE TABLE IF NOT EXISTS companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  market VARCHAR(120) DEFAULT 'local',
  reg_cert VARCHAR(255) DEFAULT NULL,
  reg_no VARCHAR(120) DEFAULT NULL,
  reg_name VARCHAR(180) NOT NULL,
  reg_date DATE DEFAULT NULL,
  starting_date DATE DEFAULT NULL,
  business_type VARCHAR(120) DEFAULT NULL,
  business_form VARCHAR(120) DEFAULT NULL,
  operation_status VARCHAR(120) DEFAULT NULL,
  fin_year_end DATE DEFAULT NULL,
  tax_no VARCHAR(120) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_companies_client (client_id),
  INDEX idx_companies_reg_name (reg_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cart (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  vendor_id INT NOT NULL,
  pro_id INT NOT NULL,
  img VARCHAR(255) DEFAULT NULL,
  brand VARCHAR(180) DEFAULT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT,
  sale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  units DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cart_client (client_id),
  INDEX idx_cart_vendor (vendor_id),
  INDEX idx_cart_product (pro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  sender_name VARCHAR(180) DEFAULT NULL,
  receiver_id INT NOT NULL,
  receiver_name VARCHAR(180) DEFAULT NULL,
  subject VARCHAR(180) DEFAULT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(40) NOT NULL DEFAULT 'unread',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_messages_sender (sender_id, created_at),
  INDEX idx_messages_receiver (receiver_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade a legacy messages table if it existed before this migration.
SET @has_sender_name = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='sender_name');
SET @sql_sender_name = IF(@has_sender_name=0,'ALTER TABLE messages ADD COLUMN sender_name VARCHAR(180) DEFAULT NULL','SELECT 1');
PREPARE stmt_sender_name FROM @sql_sender_name; EXECUTE stmt_sender_name; DEALLOCATE PREPARE stmt_sender_name;
SET @has_receiver_name = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='receiver_name');
SET @sql_receiver_name = IF(@has_receiver_name=0,'ALTER TABLE messages ADD COLUMN receiver_name VARCHAR(180) DEFAULT NULL','SELECT 1');
PREPARE stmt_receiver_name FROM @sql_receiver_name; EXECUTE stmt_receiver_name; DEALLOCATE PREPARE stmt_receiver_name;
SET @has_subject = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='subject');
SET @sql_subject = IF(@has_subject=0,'ALTER TABLE messages ADD COLUMN subject VARCHAR(180) DEFAULT NULL','SELECT 1');
PREPARE stmt_subject FROM @sql_subject; EXECUTE stmt_subject; DEALLOCATE PREPARE stmt_subject;
SET @has_message = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='message');
SET @sql_message = IF(@has_message=0,'ALTER TABLE messages ADD COLUMN message TEXT DEFAULT NULL','SELECT 1');
PREPARE stmt_message FROM @sql_message; EXECUTE stmt_message; DEALLOCATE PREPARE stmt_message;
SET @has_is_read = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='is_read');
SET @sql_is_read = IF(@has_is_read=0,'ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE stmt_is_read FROM @sql_is_read; EXECUTE stmt_is_read; DEALLOCATE PREPARE stmt_is_read;
SET @has_status_message = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='status');
SET @sql_status_message = IF(@has_status_message=0,'ALTER TABLE messages ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT \'unread\'','SELECT 1');
PREPARE stmt_status_message FROM @sql_status_message; EXECUTE stmt_status_message; DEALLOCATE PREPARE stmt_status_message;

CREATE TABLE IF NOT EXISTS qualifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  qualification VARCHAR(180) NOT NULL,
  qualification_field VARCHAR(180) NOT NULL,
  qualification_img VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_qualifications_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_leave (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  staff_id INT NOT NULL,
  leave_type VARCHAR(80) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days DECIMAL(6,2) NOT NULL DEFAULT 0,
  leave_status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  reason VARCHAR(500) DEFAULT NULL,
  reviewed_by INT DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_staff_leave_vendor_dates (vendor_id, start_date, end_date),
  INDEX idx_staff_leave_staff (staff_id, leave_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_earnings_deductions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  staff_id INT NOT NULL,
  entry_type ENUM('earning','deduction') NOT NULL,
  description VARCHAR(180) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payroll_period CHAR(7) DEFAULT NULL,
  status ENUM('draft','approved','paid','reversed') NOT NULL DEFAULT 'draft',
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_staff_payroll_vendor_period (vendor_id, payroll_period),
  INDEX idx_staff_payroll_staff (staff_id, entry_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cnrbyte_notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  recipient_type ENUM('client','vendor','staff','admin') NOT NULL,
  recipient_id INT NOT NULL,
  notification_type VARCHAR(60) NOT NULL,
  title VARCHAR(180) NOT NULL,
  message VARCHAR(1000) NOT NULL,
  target_url VARCHAR(500) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME DEFAULT NULL,
  INDEX idx_notification_recipient (recipient_type, recipient_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cnrbyte_messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_key VARCHAR(160) NOT NULL,
  sender_type ENUM('client','vendor','staff','admin') NOT NULL,
  sender_id INT NOT NULL,
  recipient_type ENUM('client','vendor','staff','admin') NOT NULL,
  recipient_id INT NOT NULL,
  subject VARCHAR(180) DEFAULT NULL,
  body TEXT NOT NULL,
  related_order_id INT DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME DEFAULT NULL,
  INDEX idx_message_conversation (conversation_key, created_at),
  INDEX idx_message_recipient (recipient_type, recipient_id, is_read, created_at),
  INDEX idx_message_sender (sender_type, sender_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_accounting_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL UNIQUE,
  order_ref VARCHAR(64) NOT NULL,
  buyer_id INT NOT NULL,
  vendor_id INT NOT NULL,
  vendor_user_id INT NOT NULL,
  gross_sales DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cost_of_goods_sold DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  platform_commission DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  vendor_payable DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  entry_status VARCHAR(32) NOT NULL DEFAULT 'pending_settlement',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_marketplace_accounting_vendor (vendor_id),
  INDEX idx_marketplace_accounting_buyer (buyer_id),
  INDEX idx_marketplace_accounting_status (entry_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Align the order schema with the columns read/written by marketplace_orders.php.
SET @has_payment_method = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_orders' AND COLUMN_NAME='payment_method');
SET @sql_payment_method = IF(@has_payment_method=0,"ALTER TABLE marketplace_orders ADD COLUMN payment_method VARCHAR(40) NOT NULL DEFAULT 'terminal'",'SELECT 1');
PREPARE stmt_payment_method FROM @sql_payment_method; EXECUTE stmt_payment_method; DEALLOCATE PREPARE stmt_payment_method;
SET @has_delivery_street = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_orders' AND COLUMN_NAME='delivery_street');
SET @sql_delivery_street = IF(@has_delivery_street=0,'ALTER TABLE marketplace_orders ADD COLUMN delivery_street VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt_delivery_street FROM @sql_delivery_street; EXECUTE stmt_delivery_street; DEALLOCATE PREPARE stmt_delivery_street;
SET @has_delivery_suburb = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_orders' AND COLUMN_NAME='delivery_suburb');
SET @sql_delivery_suburb = IF(@has_delivery_suburb=0,'ALTER TABLE marketplace_orders ADD COLUMN delivery_suburb VARCHAR(150) DEFAULT NULL','SELECT 1');
PREPARE stmt_delivery_suburb FROM @sql_delivery_suburb; EXECUTE stmt_delivery_suburb; DEALLOCATE PREPARE stmt_delivery_suburb;
SET @has_delivery_town = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_orders' AND COLUMN_NAME='delivery_town');
SET @sql_delivery_town = IF(@has_delivery_town=0,'ALTER TABLE marketplace_orders ADD COLUMN delivery_town VARCHAR(150) DEFAULT NULL','SELECT 1');
PREPARE stmt_delivery_town FROM @sql_delivery_town; EXECUTE stmt_delivery_town; DEALLOCATE PREPARE stmt_delivery_town;
SET @has_delivery_province = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_orders' AND COLUMN_NAME='delivery_province');
SET @sql_delivery_province = IF(@has_delivery_province=0,'ALTER TABLE marketplace_orders ADD COLUMN delivery_province VARCHAR(150) DEFAULT NULL','SELECT 1');
PREPARE stmt_delivery_province FROM @sql_delivery_province; EXECUTE stmt_delivery_province; DEALLOCATE PREPARE stmt_delivery_province;
SET @has_delivery_postal_code = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_orders' AND COLUMN_NAME='delivery_postal_code');
SET @sql_delivery_postal_code = IF(@has_delivery_postal_code=0,'ALTER TABLE marketplace_orders ADD COLUMN delivery_postal_code VARCHAR(50) DEFAULT NULL','SELECT 1');
PREPARE stmt_delivery_postal_code FROM @sql_delivery_postal_code; EXECUTE stmt_delivery_postal_code; DEALLOCATE PREPARE stmt_delivery_postal_code;
SET @has_unit_cost = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_order_items' AND COLUMN_NAME='unit_cost');
SET @sql_unit_cost = IF(@has_unit_cost=0,'ALTER TABLE marketplace_order_items ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00','SELECT 1');
PREPARE stmt_unit_cost FROM @sql_unit_cost; EXECUTE stmt_unit_cost; DEALLOCATE PREPARE stmt_unit_cost;
SET @has_cost_total = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_order_items' AND COLUMN_NAME='cost_total');
SET @sql_cost_total = IF(@has_cost_total=0,'ALTER TABLE marketplace_order_items ADD COLUMN cost_total DECIMAL(12,2) NOT NULL DEFAULT 0.00','SELECT 1');
PREPARE stmt_cost_total FROM @sql_cost_total; EXECUTE stmt_cost_total; DEALLOCATE PREPARE stmt_cost_total;

-- Add inventory units when the legacy product table predates the stock ledger.
SET @has_units = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'units'
);
SET @sql_units = IF(@has_units = 0,
  'ALTER TABLE products ADD COLUMN units DECIMAL(12,2) NOT NULL DEFAULT 0.00',
  'SELECT 1');
PREPARE stmt_units FROM @sql_units;
EXECUTE stmt_units;
DEALLOCATE PREPARE stmt_units;

-- Add roster indexes idempotently without enforcing foreign keys on legacy data.
SET @has_roster_vendor_date = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roster' AND INDEX_NAME = 'idx_roster_vendor_date'
);
SET @sql_roster_vendor_date = IF(@has_roster_vendor_date = 0,
  'CREATE INDEX idx_roster_vendor_date ON roster (vendor_id, day_date)',
  'SELECT 1');
PREPARE stmt_roster_vendor_date FROM @sql_roster_vendor_date;
EXECUTE stmt_roster_vendor_date;
DEALLOCATE PREPARE stmt_roster_vendor_date;

SET @has_roster_staff_date = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roster' AND INDEX_NAME = 'idx_roster_staff_date'
);
SET @sql_roster_staff_date = IF(@has_roster_staff_date = 0,
  'CREATE INDEX idx_roster_staff_date ON roster (staff_id, day_date)',
  'SELECT 1');
PREPARE stmt_roster_staff_date FROM @sql_roster_staff_date;
EXECUTE stmt_roster_staff_date;
DEALLOCATE PREPARE stmt_roster_staff_date;
