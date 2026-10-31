-- CnrByte Step 14: authentication hardening.
-- Back up the database first. Run on staging before production.
USE `cnrbyte`;

CREATE TABLE IF NOT EXISTS cnrbyte_auth_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  blocked_until DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_attempt_identifier_ip (identifier_hash, ip_hash),
  KEY idx_auth_attempt_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cnrbyte_password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_reset_token_hash (token_hash),
  KEY idx_password_reset_client (client_id),
  KEY idx_password_reset_expiry (expires_at),
  CONSTRAINT fk_password_reset_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Report normalized duplicates before enforcing email uniqueness.
-- Resolve any duplicates manually after verifying ownership; do not delete accounts blindly.
SELECT LOWER(TRIM(email)) AS normalized_email, COUNT(*) AS account_count,
       GROUP_CONCAT(id ORDER BY id) AS client_ids
FROM clients
GROUP BY LOWER(TRIM(email))
HAVING COUNT(*) > 1;

-- Add a normalized generated column so case and surrounding whitespace cannot
-- create duplicate logical email identities. Supported by modern MySQL/MariaDB.
SET @has_email_normalized = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'email_normalized'
);
SET @email_column_sql = IF(
  @has_email_normalized = 0,
  'ALTER TABLE clients ADD COLUMN email_normalized VARCHAR(150) GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED',
  'SELECT 1'
);
PREPARE stmt_email_column FROM @email_column_sql;
EXECUTE stmt_email_column;
DEALLOCATE PREPARE stmt_email_column;

-- Create the unique index only when there are no normalized duplicates.
-- If duplicates exist, clean them manually and rerun this migration.
SET @email_duplicate_count = (
  SELECT COUNT(*) FROM (
    SELECT LOWER(TRIM(email)) AS normalized_email
    FROM clients GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) > 1
  ) AS email_duplicates
);
SET @email_unique_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients'
    AND INDEX_NAME = 'uq_clients_email_normalized' AND NON_UNIQUE = 0
);
SET @email_index_sql = IF(
  @email_duplicate_count = 0 AND @email_unique_index_exists = 0,
  'ALTER TABLE clients ADD UNIQUE KEY uq_clients_email_normalized (email_normalized)',
  'SELECT 1'
);
PREPARE stmt_email_index FROM @email_index_sql;
EXECUTE stmt_email_index;
DEALLOCATE PREPARE stmt_email_index;

SELECT CASE
  WHEN @email_duplicate_count > 0 THEN 'WARNING: resolve normalized duplicate email addresses, then rerun migration to add uq_clients_email_normalized.'
  WHEN @email_unique_index_exists > 0 THEN 'Normalized email uniqueness index already exists.'
  ELSE 'Normalized email uniqueness index created.'
END AS migration_status;
