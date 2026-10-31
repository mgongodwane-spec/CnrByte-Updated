CREATE TABLE IF NOT EXISTS business_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100) NOT NULL,
  type_name VARCHAR(180) NOT NULL,
  client_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  market VARCHAR(120) NOT NULL,
  reg_cert VARCHAR(255),
  reg_no VARCHAR(120),
  reg_name VARCHAR(180) NOT NULL,
  reg_date DATE,
  starting_date DATE,
  business_type VARCHAR(120),
  business_form VARCHAR(120),
  operation_status VARCHAR(120),
  fin_year_end DATE,
  tax_no VARCHAR(120),
  phone VARCHAR(50),
  email VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
