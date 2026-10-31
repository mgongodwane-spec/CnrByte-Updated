<?php
declare(strict_types=1);

require_once __DIR__ . '/general_ledger.php';
require_once __DIR__ . '/../../marketplace/marketplace_orders.php';


if (!function_exists('marketplace_lifecycle_notify')) {
    function marketplace_lifecycle_notify(mysqli $conn, string $recipientType, int $recipientId, string $type, string $title, string $message, string $targetUrl = '/cnrbyte/messages/dashboard.pge.php'): void {
        if ($recipientId < 1) return;
        try {
            $exists = $conn->query("SHOW TABLES LIKE 'cnrbyte_notifications'");
            if (!$exists || $exists->num_rows < 1) return;
            $stmt = $conn->prepare('INSERT INTO cnrbyte_notifications (recipient_type,recipient_id,notification_type,title,message,target_url) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('sissss', $recipientType, $recipientId, $type, $title, $message, $targetUrl);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            // Notification delivery must never undo a committed financial transaction.
            error_log('CnrByte notification could not be recorded: ' . $e->getMessage());
        }
    }
}

/** Create lifecycle tables before starting any financial transaction. */
function marketplace_lifecycle_ensure_tables(mysqli $conn): void
{
    marketplace_orders_ensure_tables($conn);
    $conn->query("CREATE TABLE IF NOT EXISTS marketplace_refunds (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, order_ref VARCHAR(64) NOT NULL,
        buyer_id INT NOT NULL, vendor_id INT NOT NULL, amount DECIMAL(12,2) NOT NULL,
        refund_method VARCHAR(40) NOT NULL, refund_status ENUM('completed','failed') NOT NULL DEFAULT 'completed',
        reason VARCHAR(255) DEFAULT NULL, processed_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_marketplace_refund_order (order_id), INDEX (buyer_id), INDEX (vendor_id), INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS marketplace_vendor_settlements (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, order_ref VARCHAR(64) NOT NULL,
        vendor_id INT NOT NULL, amount DECIMAL(12,2) NOT NULL, external_payment_reference VARCHAR(120) NOT NULL,
        settlement_status ENUM('recorded','reversed') NOT NULL DEFAULT 'recorded', recorded_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_vendor_settlement_order (order_id),
        UNIQUE KEY uq_vendor_settlement_reference (external_payment_reference), INDEX (vendor_id), INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS marketplace_order_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, order_ref VARCHAR(64) NOT NULL,
        actor_id INT NOT NULL, event_type VARCHAR(40) NOT NULL, event_note VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (order_id), INDEX (actor_id), INDEX (event_type), INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Cancel an eligible paid, unshipped order and credit its amount to the buyer's internal CnrByte balance.
 * Caller MUST authenticate the actor and enforce buyer ownership or administrator permission before calling.
 * This does not issue an external card/bank refund.
 */
function marketplace_cancel_and_refund(mysqli $conn, int $orderId, int $actorId, bool $isAdministrator, string $reason = ''): array
{
    if ($orderId <= 0 || $actorId <= 0) throw new InvalidArgumentException('A valid order and authenticated actor are required.');
    marketplace_lifecycle_ensure_tables($conn);
    $conn->begin_transaction();
    try {
        $orderStmt = $conn->prepare("SELECT id, order_ref, buyer_id, vendor_id, total_amount, order_status, package_status, tracking_code, payment_method FROM marketplace_orders WHERE id = ? FOR UPDATE");
        if (!$orderStmt) throw new RuntimeException('Unable to prepare order cancellation.');
        $orderStmt->bind_param('i', $orderId);
        if (!$orderStmt->execute()) { $orderStmt->close(); throw new RuntimeException('Unable to load order for cancellation.'); }
        $order = $orderStmt->get_result()->fetch_assoc(); $orderStmt->close();
        if (!$order) throw new RuntimeException('Order was not found.');
        if (!$isAdministrator && (int)$order['buyer_id'] !== $actorId) throw new RuntimeException('You are not authorised to cancel this order.');
        if (strtolower((string)$order['order_status']) !== 'paid' || strtolower((string)$order['package_status']) !== 'pending_packaging' || trim((string)($order['tracking_code'] ?? '')) !== '') {
            throw new RuntimeException('Only paid orders awaiting packaging and without tracking can be cancelled automatically.');
        }
        $amount = round((float)$order['total_amount'], 2);
        if ($amount <= 0) throw new RuntimeException('Order amount is not valid for a refund.');

        $exists = $conn->prepare('SELECT id FROM marketplace_refunds WHERE order_id = ? LIMIT 1');
        if (!$exists) throw new RuntimeException('Unable to check existing refunds.');
        $exists->bind_param('i', $orderId); $exists->execute(); $already = $exists->get_result()->fetch_assoc(); $exists->close();
        if ($already) throw new RuntimeException('This order already has a refund record.');

        // Refund the balance internally. Checkout uses credit accounts for card mode and non-credit accounts for terminal mode.
        $method = strtolower((string)$order['payment_method']) === 'card' ? 'card' : 'terminal';
        if ($method === 'card') {
            $account = $conn->prepare("SELECT id FROM financial_accounts WHERE client_id = ? AND account_type = 'credit' ORDER BY id ASC LIMIT 1 FOR UPDATE");
        } else {
            $account = $conn->prepare("SELECT id FROM financial_accounts WHERE client_id = ? AND account_type <> 'credit' ORDER BY id ASC LIMIT 1 FOR UPDATE");
        }
        if (!$account) throw new RuntimeException('Unable to prepare buyer refund account lookup.');
        $buyerId = (int)$order['buyer_id']; $account->bind_param('i', $buyerId);
        if (!$account->execute()) { $account->close(); throw new RuntimeException('Unable to find buyer refund account.'); }
        $accountRow = $account->get_result()->fetch_assoc(); $account->close();
        if (!$accountRow) throw new RuntimeException('No eligible internal buyer account exists for this refund. No changes were committed.');
        $accountId = (int)$accountRow['id'];
        $description = 'Marketplace refund ' . (string)$order['order_ref'];
        $credit = $conn->prepare("INSERT INTO financial_transactions (account_id, txn_type, amount, description, created_at) VALUES (?, 'deposit', ?, ?, NOW())");
        if (!$credit) throw new RuntimeException('Unable to prepare buyer account credit.');
        $credit->bind_param('ids', $accountId, $amount, $description);
        if (!$credit->execute()) { $credit->close(); throw new RuntimeException('Unable to credit the buyer account.'); }
        $credit->close();

        $refundStmt = $conn->prepare("INSERT INTO marketplace_refunds (order_id, order_ref, buyer_id, vendor_id, amount, refund_method, reason, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$refundStmt) throw new RuntimeException('Unable to prepare refund record.');
        $orderRef = (string)$order['order_ref']; $vendorId = (int)$order['vendor_id']; $reason = trim($reason);
        $refundStmt->bind_param('isiidssi', $orderId, $orderRef, $buyerId, $vendorId, $amount, $method, $reason, $actorId);
        if (!$refundStmt->execute()) { $refundStmt->close(); throw new RuntimeException('Unable to save refund record.'); }
        $refundId = (int)$refundStmt->insert_id; $refundStmt->close();

        // Restore each product only if it still belongs to this vendor; movement unique key makes duplicate restoration fail safely.
        $items = $conn->prepare('SELECT product_id, quantity, unit_cost FROM marketplace_order_items WHERE order_id = ? AND vendor_id = ?');
        if (!$items) throw new RuntimeException('Unable to prepare inventory restoration.');
        $items->bind_param('ii', $orderId, $vendorId); $items->execute(); $itemRows = $items->get_result();
        $restore = $conn->prepare('UPDATE products SET units = units + ? WHERE id = ? AND vendor_id = ?');
        $movement = $conn->prepare("INSERT INTO inventory_movements (vendor_id, product_id, order_id, movement_type, quantity_delta, unit_cost, value_delta, reference, created_by) VALUES (?, ?, ?, 'refund', ?, ?, ?, ?, ?)");
        if (!$restore || !$movement) throw new RuntimeException('Unable to prepare inventory restoration statements.');
        while ($item = $itemRows->fetch_assoc()) {
            $productId = (int)$item['product_id']; $quantity = (int)$item['quantity']; $unitCost = max(0, (float)$item['unit_cost']);
            if ($quantity <= 0) throw new RuntimeException('Order contains an invalid quantity.');
            $restore->bind_param('iii', $quantity, $productId, $vendorId);
            if (!$restore->execute() || $restore->affected_rows !== 1) throw new RuntimeException('Could not restore stock for an order item.');
            $quantityDelta = (float)$quantity; $valueDelta = round($quantityDelta * $unitCost, 2); $reference = $orderRef;
            $movement->bind_param('iiidddsi', $vendorId, $productId, $orderId, $quantityDelta, $unitCost, $valueDelta, $reference, $actorId);
            if (!$movement->execute()) throw new RuntimeException('Could not record the inventory refund movement.');
        }
        $items->close(); $restore->close(); $movement->close();

        // Post a reversing journal once. The original marketplace sale journal must exist to reverse it.
        $original = $conn->prepare("SELECT id FROM journal_entries WHERE vendor_id = ? AND source_type = 'marketplace_order' AND source_id = ? AND status = 'posted' LIMIT 1 FOR UPDATE");
        if (!$original) throw new RuntimeException('Unable to locate original accounting journal.');
        $original->bind_param('ii', $vendorId, $orderId); $original->execute(); $originalRow = $original->get_result()->fetch_assoc(); $original->close();
        if (!$originalRow) throw new RuntimeException('Original posted accounting journal is missing; refund cannot be finalised safely.');
        $accounts = accounting_ensure_marketplace_accounts($conn, $vendorId);
        $totals = $conn->prepare('SELECT COALESCE(SUM(line_total),0) sales, COALESCE(SUM(cost_total),0) cost FROM marketplace_order_items WHERE order_id = ? AND vendor_id = ?');
        $totals->bind_param('ii', $orderId, $vendorId); $totals->execute(); $values = $totals->get_result()->fetch_assoc() ?: []; $totals->close();
        $sales = round((float)$values['sales'], 2); $cost = round((float)$values['cost'], 2);
        $sourceType = 'marketplace_refund'; $entryDate = date('Y-m-d'); $entryStatus = 'posted'; $description = 'Refund reversal ' . $orderRef;
        $journal = $conn->prepare('INSERT INTO journal_entries (vendor_id, source_type, source_id, reference, entry_date, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$journal) throw new RuntimeException('Unable to prepare refund reversal journal.');
        $journal->bind_param('isissssi', $vendorId, $sourceType, $orderId, $orderRef, $entryDate, $description, $entryStatus, $actorId);
        if (!$journal->execute()) { $journal->close(); throw new RuntimeException('Refund reversal journal already exists or could not be saved.'); }
        $journalId = (int)$journal->insert_id; $journal->close();
        $line = $conn->prepare('INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)');
        if (!$line) throw new RuntimeException('Unable to prepare refund journal lines.');
        $lines = [[$accounts['4000'], $sales, 0.0, 'Reverse sales revenue'], [$accounts['1100'], 0.0, $sales, 'Reverse marketplace receivable']];
        if ($cost > 0) { $lines[] = [$accounts['1200'], $cost, 0.0, 'Restore inventory value']; $lines[] = [$accounts['5000'], 0.0, $cost, 'Reverse cost of goods sold']; }
        foreach ($lines as [$accountIdLine, $debit, $creditAmount, $lineText]) {
            $line->bind_param('iidds', $journalId, $accountIdLine, $debit, $creditAmount, $lineText);
            if (!$line->execute()) throw new RuntimeException('Unable to save refund journal lines.');
        }
        $line->close();
        $balance = $conn->prepare('SELECT COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM journal_entry_lines WHERE journal_entry_id = ?');
        $balance->bind_param('i', $journalId); $balance->execute(); $journalBalance = $balance->get_result()->fetch_assoc() ?: []; $balance->close();
        if (abs(round((float)$journalBalance['d'] - (float)$journalBalance['c'], 2)) > 0.001) throw new RuntimeException('Refund reversal journal does not balance.');

        $update = $conn->prepare("UPDATE marketplace_orders SET order_status = 'cancelled' WHERE id = ? AND order_status = 'paid'");
        $update->bind_param('i', $orderId);
        if (!$update->execute() || $update->affected_rows !== 1) throw new RuntimeException('Order status could not be updated.');
        $update->close();
        $accounting = $conn->prepare("UPDATE marketplace_accounting_entries SET entry_status = 'refunded', vendor_payable = 0 WHERE order_id = ? AND vendor_id = ?");
        $accounting->bind_param('ii', $orderId, $vendorId);
        if (!$accounting->execute() || $accounting->affected_rows !== 1) throw new RuntimeException('Marketplace accounting entry could not be updated.');
        $accounting->close();
        $markOriginal = $conn->prepare("UPDATE journal_entries SET status = 'reversed' WHERE vendor_id = ? AND source_type = 'marketplace_order' AND source_id = ? AND status = 'posted'");
        $markOriginal->bind_param('ii', $vendorId, $orderId); $markOriginal->execute(); $markOriginal->close();
        $event = $conn->prepare('INSERT INTO marketplace_order_events (order_id, order_ref, actor_id, event_type, event_note) VALUES (?, ?, ?, ?, ?)');
        $eventType = 'refund_completed'; $eventNote = $reason !== '' ? $reason : 'Internal account refund and stock restoration completed';
        $event->bind_param('isiss', $orderId, $orderRef, $actorId, $eventType, $eventNote);
        if (!$event->execute()) throw new RuntimeException('Unable to record the order audit event.');
        $event->close();
        $conn->commit();
        marketplace_lifecycle_notify($conn, 'client', (int)$order['buyer_id'], 'refund', 'Refund recorded', 'An internal account refund was recorded for order ' . $orderRef . '.', '/cnrbyte/messages/dashboard.pge.php');
        marketplace_lifecycle_notify($conn, 'vendor', (int)$order['vendor_id'], 'refund', 'Order cancelled and refunded', 'Order ' . $orderRef . ' was cancelled and an internal refund was recorded.', '/cnrbyte/messages/dashboard.pge.php');
        return ['success' => true, 'refund_id' => $refundId, 'amount' => $amount, 'message' => 'Order cancelled and internal account refund recorded.'];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Marketplace refund failed: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/** Record a full vendor payout after it has actually been sent externally. Caller must enforce administrator permissions and CSRF. */
function marketplace_record_vendor_settlement(mysqli $conn, int $orderId, int $actorId, string $externalPaymentReference): array
{
    if ($orderId <= 0 || $actorId <= 0 || trim($externalPaymentReference) === '') throw new InvalidArgumentException('Order, authenticated actor, and external payment reference are required.');
    marketplace_lifecycle_ensure_tables($conn);
    $externalPaymentReference = trim($externalPaymentReference);
    if (strlen($externalPaymentReference) > 120) throw new InvalidArgumentException('External payment reference is too long.');
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT o.id, o.order_ref, o.vendor_id, o.order_status, a.vendor_payable, a.entry_status FROM marketplace_orders o JOIN marketplace_accounting_entries a ON a.order_id = o.id AND a.vendor_id = o.vendor_id WHERE o.id = ? FOR UPDATE");
        if (!$stmt) throw new RuntimeException('Unable to prepare vendor settlement.');
        $stmt->bind_param('i', $orderId); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$row) throw new RuntimeException('Order accounting record was not found.');
        if (strtolower((string)$row['order_status']) === 'cancelled' || strtolower((string)$row['entry_status']) !== 'pending_settlement') throw new RuntimeException('This order is not eligible for settlement.');
        $amount = round((float)$row['vendor_payable'], 2);
        if ($amount <= 0) throw new RuntimeException('Vendor payable must be greater than zero.');
        $vendorId = (int)$row['vendor_id']; $orderRef = (string)$row['order_ref'];
        $insert = $conn->prepare("INSERT INTO marketplace_vendor_settlements (order_id, order_ref, vendor_id, amount, external_payment_reference, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$insert) throw new RuntimeException('Unable to prepare settlement record.');
        $insert->bind_param('isidsi', $orderId, $orderRef, $vendorId, $amount, $externalPaymentReference, $actorId);
        if (!$insert->execute()) { $insert->close(); throw new RuntimeException('Settlement already exists or its payment reference is already used.'); }
        $settlementId = (int)$insert->insert_id; $insert->close();

        // Settlement journal: Dr bank/payout clearing, Cr marketplace receivable. This records a completed external payout; it does not send money.
        $accounts = accounting_ensure_marketplace_accounts($conn, $vendorId);
        $sourceType = 'marketplace_settlement'; $entryDate = date('Y-m-d'); $entryStatus = 'posted'; $description = 'Vendor settlement ' . $orderRef;
        $journal = $conn->prepare('INSERT INTO journal_entries (vendor_id, source_type, source_id, reference, entry_date, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$journal) throw new RuntimeException('Unable to prepare settlement journal.');
        $journal->bind_param('isissssi', $vendorId, $sourceType, $orderId, $orderRef, $entryDate, $description, $entryStatus, $actorId);
        if (!$journal->execute()) { $journal->close(); throw new RuntimeException('Settlement journal already exists or could not be saved.'); }
        $journalId = (int)$journal->insert_id; $journal->close();
        $line = $conn->prepare('INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)');
        if (!$line) throw new RuntimeException('Unable to prepare settlement journal lines.');
        $debit = $amount; $zero = 0.0; $debitText = 'Vendor payout recorded'; $creditText = 'Marketplace receivable settled';
        $bankAccount = $accounts['1000']; $clearingAccount = $accounts['1100'];
        $line->bind_param('iidds', $journalId, $bankAccount, $debit, $zero, $debitText); if (!$line->execute()) throw new RuntimeException('Unable to save settlement debit.');
        $line->bind_param('iidds', $journalId, $clearingAccount, $zero, $amount, $creditText); if (!$line->execute()) throw new RuntimeException('Unable to save settlement credit.');
        $line->close();
        $update = $conn->prepare("UPDATE marketplace_accounting_entries SET entry_status = 'settled' WHERE order_id = ? AND vendor_id = ? AND entry_status = 'pending_settlement'");
        $update->bind_param('ii', $orderId, $vendorId);
        if (!$update->execute() || $update->affected_rows !== 1) throw new RuntimeException('Marketplace payable could not be marked as settled.');
        $update->close();
        $event = $conn->prepare('INSERT INTO marketplace_order_events (order_id, order_ref, actor_id, event_type, event_note) VALUES (?, ?, ?, ?, ?)');
        $eventType = 'vendor_settlement_recorded'; $note = 'External payment reference: ' . $externalPaymentReference;
        $event->bind_param('isiss', $orderId, $orderRef, $actorId, $eventType, $note);
        if (!$event->execute()) throw new RuntimeException('Unable to record settlement audit event.');
        $event->close();
        $conn->commit();
        marketplace_lifecycle_notify($conn, 'vendor', (int)$row['vendor_id'], 'settlement', 'Vendor settlement recorded', 'A settlement was recorded for order ' . $orderRef . '. Verify the external transfer reference in CnrByte.', '/cnrbyte/messages/dashboard.pge.php');
        return ['success' => true, 'settlement_id' => $settlementId, 'amount' => $amount, 'message' => 'Vendor settlement recorded. Verify the external transfer independently.'];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Marketplace settlement failed: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/** Read-only vendor settlement reconciliation. Caller must authorise access to vendorId. */
function marketplace_settlement_reconciliation(mysqli $conn, int $vendorId): array
{
    if ($vendorId <= 0) throw new InvalidArgumentException('A valid vendor is required.');
    marketplace_lifecycle_ensure_tables($conn);
    $sql = "SELECT a.vendor_id,
        COALESCE(SUM(CASE WHEN a.entry_status = 'pending_settlement' THEN a.vendor_payable ELSE 0 END),0) AS pending_payable,
        COALESCE(SUM(CASE WHEN a.entry_status = 'settled' THEN a.vendor_payable ELSE 0 END),0) AS settled_payable,
        COALESCE(SUM(CASE WHEN a.entry_status = 'refunded' THEN a.gross_sales ELSE 0 END),0) AS refunded_sales,
        (SELECT COALESCE(SUM(s.amount),0) FROM marketplace_vendor_settlements s WHERE s.vendor_id = a.vendor_id AND s.settlement_status = 'recorded') AS payout_records,
        (SELECT COUNT(*) FROM marketplace_orders o LEFT JOIN marketplace_accounting_entries x ON x.order_id = o.id WHERE o.vendor_id = a.vendor_id AND o.order_status <> 'cancelled' AND x.order_id IS NULL) AS orders_missing_accounting
        FROM marketplace_accounting_entries a WHERE a.vendor_id = ? GROUP BY a.vendor_id";
    $stmt = $conn->prepare($sql); if (!$stmt) throw new RuntimeException('Unable to prepare settlement reconciliation.');
    $stmt->bind_param('i', $vendorId); if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Unable to run settlement reconciliation.'); }
    $row = $stmt->get_result()->fetch_assoc() ?: ['vendor_id'=>$vendorId,'pending_payable'=>0,'settled_payable'=>0,'refunded_sales'=>0,'payout_records'=>0,'orders_missing_accounting'=>0];
    $stmt->close();
    foreach (['pending_payable','settled_payable','refunded_sales','payout_records'] as $key) $row[$key] = round((float)$row[$key], 2);
    $row['payout_difference'] = round((float)$row['settled_payable'] - (float)$row['payout_records'], 2);
    return $row;
}
