<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/accounting/general_ledger.php';

function marketplace_orders_ensure_tables(mysqli $conn): void
{
    // The checkout code relies on products.units. Older database installs may not have this column yet.
    marketplace_orders_ensure_column($conn, 'products', 'units', "DECIMAL(12,2) NOT NULL DEFAULT 0.00");

    $conn->query(
        "CREATE TABLE IF NOT EXISTS marketplace_orders (
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
            payment_method VARCHAR(40) NOT NULL DEFAULT 'terminal',
            delivery_street VARCHAR(255) DEFAULT NULL,
            delivery_suburb VARCHAR(150) DEFAULT NULL,
            delivery_town VARCHAR(150) DEFAULT NULL,
            delivery_province VARCHAR(150) DEFAULT NULL,
            delivery_postal_code VARCHAR(50) DEFAULT NULL,
            tracking_code VARCHAR(90) DEFAULT NULL,
            qr_code_img VARCHAR(255) DEFAULT NULL,
            packaged_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (buyer_id),
            INDEX (vendor_id),
            INDEX (vendor_user_id),
            INDEX (tracking_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS marketplace_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(180) DEFAULT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) DEFAULT 0.00,
            line_total DECIMAL(12,2) DEFAULT 0.00,
            vendor_id INT NOT NULL,
            buyer_id INT NOT NULL,
            unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            cost_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (order_id),
            INDEX (product_id),
            INDEX (vendor_id),
            INDEX (buyer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    marketplace_orders_ensure_column($conn, 'marketplace_orders', 'payment_method', "VARCHAR(40) NOT NULL DEFAULT 'terminal'");
    marketplace_orders_ensure_column($conn, 'marketplace_order_items', 'unit_cost', "DECIMAL(12,2) NOT NULL DEFAULT 0.00");
    marketplace_orders_ensure_column($conn, 'marketplace_order_items', 'cost_total', "DECIMAL(12,2) NOT NULL DEFAULT 0.00");

    $conn->query(
        "CREATE TABLE IF NOT EXISTS marketplace_accounting_entries (
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
            INDEX (vendor_id), INDEX (buyer_id), INDEX (entry_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    marketplace_orders_ensure_column($conn, 'marketplace_orders', 'delivery_street', "VARCHAR(255) DEFAULT NULL");
    marketplace_orders_ensure_column($conn, 'marketplace_orders', 'delivery_suburb', "VARCHAR(150) DEFAULT NULL");
    marketplace_orders_ensure_column($conn, 'marketplace_orders', 'delivery_town', "VARCHAR(150) DEFAULT NULL");
    marketplace_orders_ensure_column($conn, 'marketplace_orders', 'delivery_province', "VARCHAR(150) DEFAULT NULL");
    marketplace_orders_ensure_column($conn, 'marketplace_orders', 'delivery_postal_code', "VARCHAR(50) DEFAULT NULL");

    // Operational inventory audit trail. Created before checkout begins its transaction.
    $conn->query("CREATE TABLE IF NOT EXISTS inventory_movements (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        product_id INT NOT NULL,
        order_id INT DEFAULT NULL,
        movement_type VARCHAR(32) NOT NULL,
        quantity_delta DECIMAL(12,2) NOT NULL,
        unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        value_delta DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        reference VARCHAR(100) DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_inventory_order_product_type (order_id, product_id, movement_type),
        INDEX (vendor_id, product_id), INDEX (created_at), INDEX (reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    accounting_ensure_ledger_tables($conn);
}

function marketplace_orders_ensure_column(mysqli $conn, string $table, string $column, string $definition): void
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");

    if ($result && $result->num_rows > 0) {
        return;
    }

    $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$column}` {$definition}");
}
