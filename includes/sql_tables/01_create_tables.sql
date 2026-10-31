-- =========================
-- CNRBYTE: CREATE TABLES ONLY
-- =========================
-- All table definitions organized by database module
-- Execute this file first, then run 02_seed_data.sql for sample data
-- Database: cnrbyte
-- Charset: utf8mb4

CREATE DATABASE IF NOT EXISTS `cnrbyte` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cnrbyte`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =========================
-- MODULE 1: USERS & ADMIN
-- =========================

CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  pass_word VARCHAR(255) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  rank VARCHAR(50) NOT NULL DEFAULT 'client',
  profile_img VARCHAR(255) DEFAULT 'default.png',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100) NOT NULL,
  type_name VARCHAR(180) NOT NULL,
  client_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_objs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type_name VARCHAR(100) NOT NULL,
  type ENUM('form','table') NOT NULL,
  current_dashboard VARCHAR(100) NOT NULL,
  selected_pge VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MODULE 2: ORGANIZATION STRUCTURE
-- =========================


CREATE TABLE IF NOT EXISTS branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_name VARCHAR(180) NOT NULL,
  branch_code VARCHAR(100) NOT NULL,
  phone VARCHAR(50),
  email VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT NOT NULL,
  shift_name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id),
  INDEX (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT NOT NULL,
  shift_id INT DEFAULT NULL,
  identity_img VARCHAR(255) DEFAULT NULL,
  identity_no VARCHAR(100) DEFAULT NULL,
  first_names VARCHAR(180) NOT NULL,
  last_name VARCHAR(180) NOT NULL,
  emp_code VARCHAR(100) DEFAULT NULL,
  start_date DATE DEFAULT NULL,
  rank VARCHAR(120) DEFAULT NULL,
  occupation VARCHAR(120) DEFAULT NULL,
  rate DECIMAL(12,2) DEFAULT 0.00,
  contract VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id),
  INDEX (branch_id),
  INDEX (shift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  vendor_id INT DEFAULT NULL,
  branch_id INT DEFAULT NULL,
  shift_id INT DEFAULT NULL,
  staff_id INT DEFAULT NULL,
  logo_img VARCHAR(255) DEFAULT NULL,
  profile_img VARCHAR(255) DEFAULT NULL,
  str_address VARCHAR(255) DEFAULT NULL,
  phy_suburb VARCHAR(150) DEFAULT NULL,
  phy_town VARCHAR(150) DEFAULT NULL,
  phy_province VARCHAR(150) DEFAULT NULL,
  phy_postal_code VARCHAR(50) DEFAULT NULL,
  phy_address_img VARCHAR(255) DEFAULT NULL,
  po_box VARCHAR(100) DEFAULT NULL,
  pos_suburb VARCHAR(150) DEFAULT NULL,
  pos_town VARCHAR(150) DEFAULT NULL,
  pos_province VARCHAR(150) DEFAULT NULL,
  pos_postal_code VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id), INDEX (vendor_id), INDEX (branch_id), INDEX (shift_id), INDEX (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ranks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  vendor_id INT DEFAULT NULL,
  branch_id INT DEFAULT NULL,
  shift_id INT DEFAULT NULL,
  staff_id INT DEFAULT NULL,
  rank VARCHAR(120) NOT NULL,
  rank_name VARCHAR(150) NOT NULL,
  qualification_rank VARCHAR(180) NOT NULL,
  qualification_field VARCHAR(180) DEFAULT NULL,
  qualification_img VARCHAR(255) DEFAULT NULL,
  occupation VARCHAR(150) NOT NULL,
  rate DECIMAL(12,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id), INDEX (vendor_id), INDEX (branch_id), INDEX (shift_id), INDEX (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MODULE 3: BANKING & FINANCIAL
-- =========================

CREATE TABLE IF NOT EXISTS banking (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  vendor_id INT DEFAULT NULL,
  branch_id INT DEFAULT NULL,
  shift_id INT DEFAULT NULL,
  staff_id INT DEFAULT NULL,
  bank_name VARCHAR(150) NOT NULL,
  branch_code VARCHAR(100) NOT NULL,
  acc_type VARCHAR(100) NOT NULL,
  acc_number VARCHAR(120) NOT NULL,
  statement_img VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id), INDEX (vendor_id), INDEX (branch_id), INDEX (shift_id), INDEX (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS banking_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  application_type ENUM('personal','business') NOT NULL,
  applicant_name VARCHAR(180) NOT NULL,
  account_subtype VARCHAR(80) DEFAULT NULL,
  savings_plan VARCHAR(80) DEFAULT NULL,
  identity_no VARCHAR(100) DEFAULT NULL,
  reg_no VARCHAR(120) DEFAULT NULL,
  doc_path VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (client_id),
  INDEX (application_type),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  account_name VARCHAR(180) NOT NULL,
  account_type ENUM('cash','bank','investment','credit') NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'ZAR',
  opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (client_id),
  INDEX (account_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  txn_type ENUM('deposit','withdrawal','expense','income') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (account_id),
  INDEX (txn_type),
  CONSTRAINT fk_financial_transactions_account
    FOREIGN KEY (account_id) REFERENCES financial_accounts(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MODULE 4: PRODUCTS & CATEGORIES
-- =========================

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  vendor_id INT NOT NULL,
  type VARCHAR(120) DEFAULT NULL,
  category VARCHAR(180) NOT NULL,
  parent_id INT NOT NULL DEFAULT 0,
  rank VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id), INDEX (vendor_id), INDEX (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  great INT DEFAULT NULL,
  grand INT DEFAULT NULL,
  parent INT DEFAULT NULL,
  child INT DEFAULT NULL,
  product_name VARCHAR(180) NOT NULL,
  description TEXT,
  measure VARCHAR(60) DEFAULT NULL,
  product_img VARCHAR(255) DEFAULT NULL,
  qr_code_img VARCHAR(255) DEFAULT 'no_qr.png',
  sale_price DECIMAL(12,2) DEFAULT 0.00,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  avg_sales DECIMAL(12,2) DEFAULT 0.00,
  avg_cost_of_sales DECIMAL(12,2) DEFAULT 0.00,
  profit_margin DECIMAL(12,2) DEFAULT 0.00,
  profit_rate DECIMAL(12,4) DEFAULT 0.0000,
  sale_margin_buffer_rate DECIMAL(12,4) DEFAULT 0.0000,
  cost_margin_buffer_rate DECIMAL(12,4) DEFAULT 0.0000,
  avg_profit_margin DECIMAL(12,2) DEFAULT 0.00,
  avg_profit_margin_rate DECIMAL(12,4) DEFAULT 0.0000,
  status VARCHAR(40) NOT NULL DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cost_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  type VARCHAR(120) DEFAULT NULL,
  cost_item_name VARCHAR(180) NOT NULL,
  description TEXT,
  measure VARCHAR(60) DEFAULT NULL,
  sale_price DECIMAL(12,2) DEFAULT 0.00,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  units DECIMAL(12,2) DEFAULT 0.00,
  inventory DECIMAL(12,2) DEFAULT 0.00,
  avg_sales DECIMAL(12,2) DEFAULT 0.00,
  avg_cost_of_sales DECIMAL(12,2) DEFAULT 0.00,
  profit_margin DECIMAL(12,2) DEFAULT 0.00,
  profit_rate DECIMAL(12,4) DEFAULT 0.0000,
  sale_margin_buffer_rate DECIMAL(12,4) DEFAULT 0.0000,
  cost_margin_buffer_rate DECIMAL(12,4) DEFAULT 0.0000,
  avg_profit_margin DECIMAL(12,2) DEFAULT 0.00,
  avg_profit_margin_rate DECIMAL(12,4) DEFAULT 0.0000,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cost_of_sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  product_id INT NOT NULL,
  cost_item_id INT NOT NULL,
  product_name VARCHAR(180) DEFAULT NULL,
  type VARCHAR(120) DEFAULT NULL,
  cost_item_name VARCHAR(180) DEFAULT NULL,
  description TEXT,
  measure VARCHAR(60) DEFAULT NULL,
  sale_price DECIMAL(12,2) DEFAULT 0.00,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  units_taken DECIMAL(12,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (product_id), INDEX (cost_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_great_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_grand_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  great_id INT NOT NULL,
  category VARCHAR(255) NOT NULL,
  INDEX (great_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_parent_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grand_id INT NOT NULL,
  great_id INT NOT NULL,
  category VARCHAR(255) NOT NULL,
  INDEX (grand_id),
  INDEX (great_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_child_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  grand_id INT NOT NULL,
  great_id INT NOT NULL,
  category VARCHAR(255) NOT NULL,
  INDEX (parent_id),
  INDEX (grand_id),
  INDEX (great_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MODULE 5: ACCOUNTING JOURNALS
-- =========================

CREATE TABLE IF NOT EXISTS crj (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  acc_id INT DEFAULT NULL,
  product_id INT DEFAULT NULL,
  details VARCHAR(255) DEFAULT NULL,
  bank DECIMAL(12,2) DEFAULT 0.00,
  debtors DECIMAL(12,2) DEFAULT 0.00,
  account_type VARCHAR(120) DEFAULT NULL,
  account_name VARCHAR(180) DEFAULT NULL,
  amount DECIMAL(12,2) DEFAULT 0.00,
  trans_date DATE DEFAULT NULL,
  sales DECIMAL(12,2) DEFAULT 0.00,
  cost_of_sales DECIMAL(12,2) DEFAULT 0.00,
  units DECIMAL(12,2) DEFAULT 0.00,
  sale_price DECIMAL(12,2) DEFAULT 0.00,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (acc_id), INDEX (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cpj (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  acc_id INT DEFAULT NULL,
  cost_item_id INT DEFAULT NULL,
  details VARCHAR(255) DEFAULT NULL,
  bank DECIMAL(12,2) DEFAULT 0.00,
  creditors DECIMAL(12,2) DEFAULT 0.00,
  account_type VARCHAR(120) DEFAULT NULL,
  account_name VARCHAR(180) DEFAULT NULL,
  amount DECIMAL(12,2) DEFAULT 0.00,
  trans_date DATE DEFAULT NULL,
  units DECIMAL(12,2) DEFAULT 0.00,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  inventory DECIMAL(12,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (acc_id), INDEX (cost_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dj (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  product_id INT DEFAULT NULL,
  details VARCHAR(255) DEFAULT NULL,
  debtors DECIMAL(12,2) DEFAULT 0.00,
  sales DECIMAL(12,2) DEFAULT 0.00,
  cost_of_sales DECIMAL(12,2) DEFAULT 0.00,
  units DECIMAL(12,2) DEFAULT 0.00,
  sale_price DECIMAL(12,2) DEFAULT 0.00,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  trans_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cj (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  cost_item_id INT DEFAULT NULL,
  details VARCHAR(255) DEFAULT NULL,
  creditors DECIMAL(12,2) DEFAULT 0.00,
  units DECIMAL(12,2) DEFAULT 0.00,
  cost_price DECIMAL(12,2) DEFAULT 0.00,
  inventory DECIMAL(12,2) DEFAULT 0.00,
  trans_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (cost_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS histograms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  product_id INT DEFAULT NULL,
  cost_item_id INT DEFAULT NULL,
  item_name VARCHAR(180) DEFAULT NULL,
  average_sale DECIMAL(12,2) DEFAULT NULL,
  actual_sale DECIMAL(12,2) DEFAULT NULL,
  average_cost DECIMAL(12,2) DEFAULT NULL,
  actual_cost DECIMAL(12,2) DEFAULT NULL,
  change_rate DECIMAL(12,4) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (product_id), INDEX (cost_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bal_stmt (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  acc_id INT DEFAULT NULL,
  date_obtained DATE DEFAULT NULL,
  account_name VARCHAR(180) DEFAULT NULL,
  cost DECIMAL(12,2) DEFAULT 0.00,
  rate DECIMAL(12,4) DEFAULT 0.0000,
  serial_no VARCHAR(180) DEFAULT NULL,
  life_span VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (acc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  acc_id INT DEFAULT NULL,
  share_type VARCHAR(120) DEFAULT NULL,
  share_action VARCHAR(120) DEFAULT NULL,
  num_of_shares INT DEFAULT 0,
  par_value DECIMAL(12,2) DEFAULT 0.00,
  issue_date DATE DEFAULT NULL,
  maturity_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (acc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MODULE 6: HR & PAYROLL
-- =========================

CREATE TABLE IF NOT EXISTS hr_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  vendor_id INT DEFAULT NULL,
  day_name VARCHAR(30) DEFAULT NULL,
  time_of_day VARCHAR(30) DEFAULT NULL,
  day_type VARCHAR(30) DEFAULT NULL,
  rate_description VARCHAR(120) DEFAULT NULL,
  rate DECIMAL(12,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id), INDEX (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roster (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  shift_id INT DEFAULT NULL,
  staff_id INT DEFAULT NULL,
  shift_name VARCHAR(150) DEFAULT NULL,
  day_date DATE DEFAULT NULL,
  day_name VARCHAR(30) DEFAULT NULL,
  day_type VARCHAR(30) DEFAULT NULL,
  time_of_day VARCHAR(30) DEFAULT NULL,
  shift_status VARCHAR(40) DEFAULT NULL,
  time_in TIME DEFAULT NULL,
  time_out TIME DEFAULT NULL,
  lunch_in TIME DEFAULT NULL,
  lunch_out TIME DEFAULT NULL,
  total_hours DECIMAL(8,2) DEFAULT 0.00,
  rate DECIMAL(12,2) DEFAULT 0.00,
  rate_description VARCHAR(120) DEFAULT NULL,
  gross_earnings DECIMAL(12,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (shift_id), INDEX (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_payslip (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  branch_id INT DEFAULT NULL,
  staff_id INT NOT NULL,
  earnings DECIMAL(12,2) DEFAULT 0.00,
  deductions DECIMAL(12,2) DEFAULT 0.00,
  amount DECIMAL(12,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vendor_id), INDEX (branch_id), INDEX (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MODULE 7: COMMUNICATIONS
-- =========================

CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  vendor_id INT DEFAULT NULL,
  branch_id INT DEFAULT NULL,
  shift_id INT DEFAULT NULL,
  staff_id INT DEFAULT NULL,
  owners_id INT DEFAULT NULL,
  contact_id INT DEFAULT NULL,
  contact_name VARCHAR(180) DEFAULT NULL,
  contact_username VARCHAR(180) DEFAULT NULL,
  contact_email VARCHAR(180) DEFAULT NULL,
  contact_phone VARCHAR(50) DEFAULT NULL,
  contact_address VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id), INDEX (vendor_id), INDEX (branch_id), INDEX (shift_id), INDEX (staff_id), INDEX (owners_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  vendor_id INT DEFAULT NULL,
  branch_id INT DEFAULT NULL,
  shift_id INT DEFAULT NULL,
  staff_id INT DEFAULT NULL,
  owners_id INT DEFAULT NULL,
  document_name VARCHAR(180) NOT NULL,
  serial_number VARCHAR(180) NOT NULL,
  document_img VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id), INDEX (vendor_id), INDEX (branch_id), INDEX (shift_id), INDEX (staff_id), INDEX (owners_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MODULE 8: TRUSTS
-- =========================

CREATE TABLE IF NOT EXISTS trusts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trust_name VARCHAR(180) NOT NULL,
  trustee_name VARCHAR(180) NOT NULL,
  trust_type VARCHAR(120) NOT NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trust_id INT NOT NULL,
  member_id_code VARCHAR(120) NOT NULL,
  name VARCHAR(180) NOT NULL,
  surname VARCHAR(180) NOT NULL,
  deposit DECIMAL(12,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (trust_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS beneficiaries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  beneficiary_id_code VARCHAR(120) NOT NULL,
  name VARCHAR(180) NOT NULL,
  surname VARCHAR(180) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trust_id INT NOT NULL,
  member_id_code VARCHAR(120) NOT NULL,
  transaction_type VARCHAR(80) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  interest_rate DECIMAL(8,4) DEFAULT 0.0000,
  loan_period INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (trust_id), INDEX (member_id_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS repayments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT NOT NULL,
  member_id_code VARCHAR(120) NOT NULL,
  month_no INT NOT NULL,
  payment DECIMAL(12,2) NOT NULL,
  principal DECIMAL(12,2) NOT NULL,
  interest DECIMAL(12,2) NOT NULL,
  balance DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (transaction_id), INDEX (member_id_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trust Applications (Client-tracked trust applications with members & beneficiaries)
CREATE TABLE IF NOT EXISTS trust_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  trust_name VARCHAR(180) NOT NULL,
  trustee_name VARCHAR(180) NOT NULL,
  trust_type VARCHAR(120) NOT NULL,
  member_count INT DEFAULT 0,
  beneficiary_count INT DEFAULT 0,
  doc_path VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (client_id),
  INDEX (trust_type),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trust_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trust_application_id INT NOT NULL,
  member_name VARCHAR(180) NOT NULL,
  member_surname VARCHAR(180) DEFAULT NULL,
  member_id_no VARCHAR(100) DEFAULT NULL,
  deposit_amount DECIMAL(14,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (trust_application_id),
  CONSTRAINT fk_trust_members_application
    FOREIGN KEY (trust_application_id) REFERENCES trust_applications(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trust_beneficiaries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trust_application_id INT NOT NULL,
  member_id INT NOT NULL,
  beneficiary_name VARCHAR(180) NOT NULL,
  relationship VARCHAR(120) DEFAULT NULL,
  allocation_percentage DECIMAL(6,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (trust_application_id),
  INDEX (member_id),
  CONSTRAINT fk_trust_beneficiaries_application
    FOREIGN KEY (trust_application_id) REFERENCES trust_applications(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_trust_beneficiaries_member
    FOREIGN KEY (member_id) REFERENCES trust_members(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- MODULE 9: QUOTATIONS
-- =========================


CREATE TABLE IF NOT EXISTS quotations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  quote_name VARCHAR(255) NOT NULL,
  grand_total DECIMAL(15,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quotation_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(10,2) NOT NULL,
  unit_price DECIMAL(15,2) NOT NULL,
  subtotal DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (quote_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(64) NOT NULL UNIQUE,
  buyer_id INT NOT NULL,
  vendor_id INT NOT NULL,
  vendor_user_id INT NOT NULL,
  buyer_username VARCHAR(180) DEFAULT NULL,
  vendor_name VARCHAR(180) DEFAULT NULL,
  total_amount DECIMAL(12,2) DEFAULT 0.00,
  order_status VARCHAR(40) NOT NULL DEFAULT 'paid',
  package_status VARCHAR(40) NOT NULL DEFAULT 'pending_packaging',
  tracking_code VARCHAR(90) DEFAULT NULL,
  qr_code_img VARCHAR(255) DEFAULT NULL,
  packaged_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (buyer_id),
  INDEX (vendor_id),
  INDEX (vendor_user_id),
  INDEX (tracking_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(180) DEFAULT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) DEFAULT 0.00,
  line_total DECIMAL(12,2) DEFAULT 0.00,
  vendor_id INT NOT NULL,
  buyer_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (order_id),
  INDEX (product_id),
  INDEX (vendor_id),
  INDEX (buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- CNRBYTE ASSET MANAGEMENT MODULE
-- Paste into phpMyAdmin
-- Compatible with existing cnrbyte schema
-- ==========================================

USE `cnrbyte`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ==========================================
-- 1. ASSET MASTER REGISTER
-- ==========================================
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,

    client_id INT DEFAULT NULL,
    vendor_id INT DEFAULT NULL,
    branch_id INT DEFAULT NULL,
    shift_id INT DEFAULT NULL,
    staff_id INT DEFAULT NULL,

    acc_id INT DEFAULT NULL,

    asset_code VARCHAR(60) NOT NULL UNIQUE,
    asset_name VARCHAR(180) NOT NULL,
    category VARCHAR(120) DEFAULT NULL,
    sub_category VARCHAR(120) DEFAULT NULL,

    description TEXT DEFAULT NULL,
    serial_no VARCHAR(180) DEFAULT NULL,
    barcode_no VARCHAR(180) DEFAULT NULL,

    supplier_name VARCHAR(180) DEFAULT NULL,
    invoice_no VARCHAR(120) DEFAULT NULL,

    purchase_date DATE DEFAULT NULL,
    in_service_date DATE DEFAULT NULL,
    warranty_expiry DATE DEFAULT NULL,

    cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    residual_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    useful_life_months INT NOT NULL DEFAULT 12,
    depreciation_method ENUM('straight_line','reducing_balance','none')
        NOT NULL DEFAULT 'straight_line',

    monthly_depreciation DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    accumulated_depreciation DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    carrying_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    last_depreciation_period VARCHAR(6) DEFAULT NULL,

    status ENUM(
        'DRAFT',
        'ACTIVE',
        'PAUSED',
        'FULLY_DEPRECIATED',
        'DISPOSED'
    ) NOT NULL DEFAULT 'DRAFT',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX (client_id),
    INDEX (vendor_id),
    INDEX (branch_id),
    INDEX (shift_id),
    INDEX (staff_id),
    INDEX (acc_id),
    INDEX (category),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 2. MONTHLY DEPRECIATION HISTORY
-- ==========================================
CREATE TABLE IF NOT EXISTS asset_depreciation (
    id INT AUTO_INCREMENT PRIMARY KEY,

    asset_id INT NOT NULL,
    vendor_id INT DEFAULT NULL,
    branch_id INT DEFAULT NULL,

    period_yyyymm VARCHAR(6) NOT NULL,

    opening_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    depreciation_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    accumulated_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    closing_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    posted_status ENUM('pending','posted') DEFAULT 'posted',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_asset_period (asset_id, period_yyyymm),
    INDEX (asset_id),
    INDEX (vendor_id),
    INDEX (branch_id),

    CONSTRAINT fk_asset_dep_asset
        FOREIGN KEY (asset_id)
        REFERENCES assets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 3. MOVEMENTS / TRANSFERS
-- ==========================================
CREATE TABLE IF NOT EXISTS asset_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,

    asset_id INT NOT NULL,

    from_vendor_id INT DEFAULT NULL,
    from_branch_id INT DEFAULT NULL,
    from_shift_id INT DEFAULT NULL,
    from_staff_id INT DEFAULT NULL,

    to_vendor_id INT DEFAULT NULL,
    to_branch_id INT DEFAULT NULL,
    to_shift_id INT DEFAULT NULL,
    to_staff_id INT DEFAULT NULL,

    movement_type ENUM(
        'transfer',
        'assignment',
        'return',
        'location_change'
    ) DEFAULT 'transfer',

    movement_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX (asset_id),

    CONSTRAINT fk_asset_move_asset
        FOREIGN KEY (asset_id)
        REFERENCES assets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 4. DISPOSALS / SALES / WRITE-OFFS
-- ==========================================
CREATE TABLE IF NOT EXISTS asset_disposals (
    id INT AUTO_INCREMENT PRIMARY KEY,

    asset_id INT NOT NULL,

    disposal_type ENUM(
        'sale',
        'scrap',
        'write_off',
        'loss',
        'donation'
    ) NOT NULL,

    disposal_date DATE NOT NULL,

    sale_value DECIMAL(14,2) DEFAULT 0.00,
    disposal_cost DECIMAL(14,2) DEFAULT 0.00,
    gain_loss DECIMAL(14,2) DEFAULT 0.00,

    notes TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_asset_disposal (asset_id),
    INDEX (asset_id),

    CONSTRAINT fk_asset_disposal_asset
        FOREIGN KEY (asset_id)
        REFERENCES assets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 5. MAINTENANCE / REPAIRS
-- ==========================================
CREATE TABLE IF NOT EXISTS asset_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,

    asset_id INT NOT NULL,

    service_type VARCHAR(120) DEFAULT NULL,
    supplier_name VARCHAR(180) DEFAULT NULL,

    service_date DATE NOT NULL,
    next_service_date DATE DEFAULT NULL,

    cost DECIMAL(14,2) DEFAULT 0.00,

    notes TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX (asset_id),
    INDEX (service_date),

    CONSTRAINT fk_asset_maint_asset
        FOREIGN KEY (asset_id)
        REFERENCES assets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 6. OPTIONAL ASSET DOCUMENTS
-- ==========================================
CREATE TABLE IF NOT EXISTS asset_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,

    asset_id INT NOT NULL,

    document_name VARCHAR(180) NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    document_type VARCHAR(100) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX (asset_id),

    CONSTRAINT fk_asset_doc_asset
        FOREIGN KEY (asset_id)
        REFERENCES assets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 7. DEFAULT REPORTING VIEW
-- ==========================================
CREATE OR REPLACE VIEW vw_asset_summary AS
SELECT
    id,
    asset_code,
    asset_name,
    category,
    vendor_id,
    branch_id,
    staff_id,
    cost,
    accumulated_depreciation,
    carrying_value,
    status
FROM assets;