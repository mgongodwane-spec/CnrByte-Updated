-- CnrByte Step 11: idempotent refunds, inventory reversals and vendor settlement records.
-- Run on a staging copy first. These tables record internal-account refunds and externally executed vendor payouts.

CREATE TABLE IF NOT EXISTS marketplace_refunds (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  order_ref VARCHAR(64) NOT NULL,
  buyer_id INT NOT NULL,
  vendor_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  refund_method VARCHAR(40) NOT NULL,
  refund_status ENUM('completed','failed') NOT NULL DEFAULT 'completed',
  reason VARCHAR(255) DEFAULT NULL,
  processed_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_marketplace_refund_order (order_id),
  INDEX (buyer_id), INDEX (vendor_id), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_vendor_settlements (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  order_ref VARCHAR(64) NOT NULL,
  vendor_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  external_payment_reference VARCHAR(120) NOT NULL,
  settlement_status ENUM('recorded','reversed') NOT NULL DEFAULT 'recorded',
  recorded_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vendor_settlement_order (order_id),
  UNIQUE KEY uq_vendor_settlement_reference (external_payment_reference),
  INDEX (vendor_id), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_order_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  order_ref VARCHAR(64) NOT NULL,
  actor_id INT NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  event_note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (order_id), INDEX (actor_id), INDEX (event_type), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
