<?php
declare(strict_types=1);

/** Create the vendor-scoped double-entry ledger tables. Call before opening a business transaction. */
function accounting_ensure_ledger_tables(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS chart_of_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        account_code VARCHAR(20) NOT NULL,
        account_name VARCHAR(120) NOT NULL,
        account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
        is_system TINYINT(1) NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_vendor_account_code (vendor_id, account_code),
        INDEX (vendor_id, account_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS journal_entries (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        source_type VARCHAR(40) NOT NULL,
        source_id BIGINT NOT NULL,
        reference VARCHAR(100) NOT NULL,
        entry_date DATE NOT NULL,
        description VARCHAR(255) NOT NULL,
        status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'posted',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_journal_source (vendor_id, source_type, source_id),
        INDEX (vendor_id, entry_date), INDEX (reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS journal_entry_lines (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        journal_entry_id BIGINT NOT NULL,
        account_id INT NOT NULL,
        debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        description VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (journal_entry_id), INDEX (account_id),
        CONSTRAINT fk_journal_lines_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_journal_lines_account FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function accounting_ensure_marketplace_accounts(mysqli $conn, int $vendorId): array
{
    if ($vendorId <= 0) {
        throw new InvalidArgumentException('A valid vendor is required for accounting.');
    }
    $accounts = [
        ['1000', 'Bank / Payout Clearing', 'asset'],
        ['1100', 'Marketplace Clearing Receivable', 'asset'],
        ['1200', 'Inventory', 'asset'],
        ['4000', 'Sales Revenue', 'revenue'],
        ['5000', 'Cost of Goods Sold', 'expense'],
    ];
    $insert = $conn->prepare('INSERT INTO chart_of_accounts (vendor_id, account_code, account_name, account_type, is_system) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE account_name = VALUES(account_name), account_type = VALUES(account_type)');
    if (!$insert) throw new RuntimeException('Unable to prepare chart-of-accounts setup.');
    foreach ($accounts as [$code, $name, $type]) {
        $insert->bind_param('isss', $vendorId, $code, $name, $type);
        if (!$insert->execute()) { $insert->close(); throw new RuntimeException('Unable to initialise vendor accounting accounts.'); }
    }
    $insert->close();

    $result = [];
    $select = $conn->prepare('SELECT id, account_code FROM chart_of_accounts WHERE vendor_id = ? AND account_code IN (\'1000\', \'1100\', \'1200\', \'4000\', \'5000\')');
    if (!$select) throw new RuntimeException('Unable to read chart of accounts.');
    $select->bind_param('i', $vendorId);
    if (!$select->execute()) { $select->close(); throw new RuntimeException('Unable to read chart of accounts.'); }
    $rows = $select->get_result();
    while ($row = $rows->fetch_assoc()) $result[(string)$row['account_code']] = (int)$row['id'];
    $select->close();
    foreach (['1000','1100','1200','4000','5000'] as $code) if (empty($result[$code])) throw new RuntimeException('Required accounting account is missing.');
    return $result;
}

/** Post vendor-side sales and COGS journal once per marketplace order. Must run inside checkout transaction. */
function accounting_post_marketplace_order(mysqli $conn, int $orderId, int $vendorId, string $orderRef, ?int $createdBy = null): void
{
    if ($orderId <= 0 || $vendorId <= 0) throw new InvalidArgumentException('Invalid order accounting reference.');

    $exists = $conn->prepare("SELECT id FROM journal_entries WHERE vendor_id = ? AND source_type = 'marketplace_order' AND source_id = ? LIMIT 1");
    if (!$exists) throw new RuntimeException('Unable to check for an existing accounting journal.');
    $exists->bind_param('ii', $vendorId, $orderId);
    if (!$exists->execute()) { $exists->close(); throw new RuntimeException('Unable to check for an existing accounting journal.'); }
    $found = $exists->get_result()->fetch_assoc();
    $exists->close();
    if ($found) return; // Idempotency guard.

    $sum = $conn->prepare('SELECT COALESCE(SUM(line_total),0) AS sales_total, COALESCE(SUM(cost_total),0) AS cost_total FROM marketplace_order_items WHERE order_id = ? AND vendor_id = ?');
    if (!$sum) throw new RuntimeException('Unable to prepare order accounting totals.');
    $sum->bind_param('ii', $orderId, $vendorId);
    if (!$sum->execute()) { $sum->close(); throw new RuntimeException('Unable to calculate order accounting totals.'); }
    $totals = $sum->get_result()->fetch_assoc() ?: [];
    $sum->close();
    $sales = round((float)($totals['sales_total'] ?? 0), 2);
    $cost = round((float)($totals['cost_total'] ?? 0), 2);
    if ($sales <= 0 || $cost < 0) {
        throw new RuntimeException('Order totals are not valid for accounting. Check sales and cost snapshots.');
    }
    $accounts = accounting_ensure_marketplace_accounts($conn, $vendorId);
    $entryDate = date('Y-m-d');
    $description = 'Marketplace sale ' . $orderRef;
    $sourceType = 'marketplace_order';
    $status = 'posted';
    $entry = $conn->prepare('INSERT INTO journal_entries (vendor_id, source_type, source_id, reference, entry_date, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$entry) throw new RuntimeException('Unable to prepare accounting journal.');
    $createdByValue = $createdBy && $createdBy > 0 ? $createdBy : null;
    $entry->bind_param('isissssi', $vendorId, $sourceType, $orderId, $orderRef, $entryDate, $description, $status, $createdByValue);
    if (!$entry->execute()) { $entry->close(); throw new RuntimeException('Unable to create accounting journal.'); }
    $journalId = (int)$entry->insert_id;
    $entry->close();

    // Dr clearing / Cr revenue; Dr COGS / Cr inventory. Both pairs balance independently.
    $lines = [
        [$accounts['1100'], $sales, 0.00, 'Marketplace settlement receivable'],
        [$accounts['4000'], 0.00, $sales, 'Sales revenue'],
    ];
    if ($cost > 0) {
        $lines[] = [$accounts['5000'], $cost, 0.00, 'Cost of goods sold'];
        $lines[] = [$accounts['1200'], 0.00, $cost, 'Inventory relieved on sale'];
    }
    $line = $conn->prepare('INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)');
    if (!$line) throw new RuntimeException('Unable to prepare accounting journal lines.');
    foreach ($lines as [$accountId, $debit, $credit, $lineDescription]) {
        $accountId = (int)$accountId;
        $debit = round((float)$debit, 2);
        $credit = round((float)$credit, 2);
        $line->bind_param('iidds', $journalId, $accountId, $debit, $credit, $lineDescription);
        if (!$line->execute()) { $line->close(); throw new RuntimeException('Unable to save accounting journal lines.'); }
    }
    $line->close();

    $balance = $conn->prepare('SELECT COALESCE(SUM(debit),0) AS debits, COALESCE(SUM(credit),0) AS credits FROM journal_entry_lines WHERE journal_entry_id = ?');
    if (!$balance) throw new RuntimeException('Unable to verify journal balance.');
    $balance->bind_param('i', $journalId);
    if (!$balance->execute()) { $balance->close(); throw new RuntimeException('Unable to verify journal balance.'); }
    $values = $balance->get_result()->fetch_assoc() ?: [];
    $balance->close();
    if (abs(round((float)$values['debits'] - (float)$values['credits'], 2)) > 0.001) {
        throw new RuntimeException('Accounting journal is not balanced.');
    }
}

/** Returns per-vendor order, subledger, stock-movement and GL totals for a date range. */
function accounting_marketplace_reconciliation(mysqli $conn, int $vendorId, string $fromDate, string $toDate): array
{
    if ($vendorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) || $fromDate > $toDate) {
        throw new InvalidArgumentException('Valid vendor and date range are required.');
    }
    $sql = "SELECT
        (SELECT COALESCE(SUM(o.total_amount),0) FROM marketplace_orders o WHERE o.vendor_id = ? AND o.created_at >= CONCAT(?, ' 00:00:00') AND o.created_at < DATE_ADD(CONCAT(?, ' 00:00:00'), INTERVAL 1 DAY) AND o.order_status <> 'cancelled') AS order_sales,
        (SELECT COALESCE(SUM(a.gross_sales),0) FROM marketplace_accounting_entries a JOIN marketplace_orders o ON o.id = a.order_id WHERE a.vendor_id = ? AND o.created_at >= CONCAT(?, ' 00:00:00') AND o.created_at < DATE_ADD(CONCAT(?, ' 00:00:00'), INTERVAL 1 DAY) AND o.order_status <> 'cancelled') AS subledger_sales,
        (SELECT COALESCE(SUM(jl.credit),0) FROM journal_entries j JOIN journal_entry_lines jl ON jl.journal_entry_id = j.id JOIN chart_of_accounts coa ON coa.id = jl.account_id WHERE j.vendor_id = ? AND j.source_type = 'marketplace_order' AND j.status = 'posted' AND coa.account_code = '4000' AND j.entry_date BETWEEN ? AND ?) AS ledger_sales,
        (SELECT COALESCE(SUM(-im.quantity_delta),0) FROM inventory_movements im WHERE im.vendor_id = ? AND im.movement_type = 'sale' AND im.created_at >= CONCAT(?, ' 00:00:00') AND im.created_at < DATE_ADD(CONCAT(?, ' 00:00:00'), INTERVAL 1 DAY)) AS units_sold,
        (SELECT COALESCE(SUM(-im.value_delta),0) FROM inventory_movements im WHERE im.vendor_id = ? AND im.movement_type = 'sale' AND im.created_at >= CONCAT(?, ' 00:00:00') AND im.created_at < DATE_ADD(CONCAT(?, ' 00:00:00'), INTERVAL 1 DAY)) AS inventory_cost_relieved";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Unable to prepare marketplace reconciliation report.');
    $stmt->bind_param('issississississ', $vendorId, $fromDate, $toDate, $vendorId, $fromDate, $toDate, $vendorId, $fromDate, $toDate, $vendorId, $fromDate, $toDate, $vendorId, $fromDate, $toDate);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Unable to run marketplace reconciliation report.'); }
    $result = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $result['sales_difference_orders_vs_subledger'] = round((float)$result['order_sales'] - (float)$result['subledger_sales'], 2);
    $result['sales_difference_orders_vs_ledger'] = round((float)$result['order_sales'] - (float)$result['ledger_sales'], 2);
    return $result;
}
