<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/../includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../includes/global.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../includes/global.shw.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../includes/accounting/marketplace_lifecycle.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (($_SESSION['user_info']['rank'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied. Administrator permission is required.');
}
$actorId = (int)($_SESSION['user_id'] ?? $_SESSION['user_info']['id'] ?? 0);
if ($actorId <= 0) { http_response_code(401); exit('A valid administrator session is required.'); }
if (empty($_SESSION['cnrbyte_csrf_token'])) { $_SESSION['cnrbyte_csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = (string)$_SESSION['cnrbyte_csrf_token'];
$message = '';
$messageType = 'info';

try {
    marketplace_lifecycle_ensure_tables($conn);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submitted = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrf, $submitted)) { throw new RuntimeException('Security token validation failed. Refresh the page and try again.'); }
        $action = (string)($_POST['action'] ?? '');
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($action === 'refund') {
            $result = marketplace_cancel_and_refund($conn, $orderId, $actorId, true, trim((string)($_POST['reason'] ?? '')));
        } elseif ($action === 'settlement') {
            $result = marketplace_record_vendor_settlement($conn, $orderId, $actorId, trim((string)($_POST['payment_reference'] ?? '')));
        } else { throw new RuntimeException('Unknown action.'); }
        $message = (string)($result['message'] ?? 'Action completed.');
        $messageType = !empty($result['success']) ? 'success' : 'danger';
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'danger';
}

$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$orders = [];
$sql = "SELECT o.id, o.order_ref, o.buyer_id, o.vendor_id, o.total_amount, o.order_status, o.package_status, o.tracking_code, o.payment_method, o.created_at, a.entry_status, a.vendor_payable,
        (SELECT COUNT(*) FROM marketplace_refunds r WHERE r.order_id=o.id) AS refund_count,
        (SELECT COUNT(*) FROM marketplace_vendor_settlements s WHERE s.order_id=o.id AND s.settlement_status='recorded') AS settlement_count
        FROM marketplace_orders o LEFT JOIN marketplace_accounting_entries a ON a.order_id=o.id AND a.vendor_id=o.vendor_id WHERE 1=1";
$types = ''; $params = [];
if ($search !== '') { $sql .= ' AND (o.order_ref LIKE ? OR CAST(o.buyer_id AS CHAR) LIKE ? OR CAST(o.vendor_id AS CHAR) LIKE ?)'; $like = '%' . $search . '%'; $types .= 'sss'; array_push($params, $like, $like, $like); }
if ($statusFilter !== '' && in_array($statusFilter, ['paid','cancelled','pending_settlement','settled','refunded'], true)) {
    if ($statusFilter === 'pending_settlement' || $statusFilter === 'settled' || $statusFilter === 'refunded') { $sql .= ' AND a.entry_status = ?'; }
    else { $sql .= ' AND o.order_status = ?'; }
    $types .= 's'; $params[] = $statusFilter;
}
$sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT 150';
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    if ($stmt->execute()) { $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $orders[] = $row; }
    $stmt->close();
}
$vendorIds = [];
foreach ($orders as $row) { $vendorIds[(int)$row['vendor_id']] = true; }
$reconciliations = [];
foreach (array_keys($vendorIds) as $vendorId) {
    try { $reconciliations[$vendorId] = marketplace_settlement_reconciliation($conn, (int)$vendorId); }
    catch (Throwable $e) { error_log('CnrByte reconciliation view: ' . $e->getMessage()); }
}
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($value): string { return 'R ' . number_format((float)$value, 2); }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Marketplace Refunds & Settlements | CnrByte</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#172033;background:#f3f6fa}*{box-sizing:border-box}body{margin:0}.wrap{max-width:1440px;margin:auto;padding:22px}.top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.top h1{margin:0;font-size:clamp(1.4rem,3vw,2rem)}a.button,.button{display:inline-block;border:0;border-radius:8px;padding:9px 13px;background:#1d3557;color:white;text-decoration:none;font-weight:600;cursor:pointer}.panel{background:white;border:1px solid #e1e7ef;border-radius:12px;padding:18px;margin:18px 0;box-shadow:0 2px 8px #1720330a}.notice{padding:12px 14px;border-radius:8px;margin:16px 0}.success{background:#e7f7ee;color:#14532d}.danger{background:#feecec;color:#991b1b}.info{background:#eaf2ff;color:#1e40af}.filters{display:flex;gap:10px;flex-wrap:wrap}.filters input,.filters select,input[type=text],textarea{border:1px solid #ccd5e1;border-radius:7px;padding:9px;max-width:100%}.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:950px}th,td{text-align:left;border-bottom:1px solid #e7ecf2;padding:10px;vertical-align:top;font-size:.9rem}th{background:#f7f9fc}.muted{color:#64748b;font-size:.86rem}.pill{display:inline-block;background:#eef2f7;border-radius:30px;padding:3px 8px;font-size:.78rem}.action-form{min-width:220px;display:grid;gap:7px}.action-form input{width:100%}.action-form button{width:100%}.warning{font-size:.8rem;color:#854d0e}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}.metric{padding:12px;background:#f7f9fc;border-radius:8px}.metric strong{display:block;font-size:1.15rem}.subhead{margin:0 0 12px} @media(max-width:600px){.wrap{padding:12px}.panel{padding:12px}}
</style></head><body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?><main class="wrap">
<header class="top"><div><h1>Marketplace Refunds & Vendor Settlements</h1><div class="muted">Administrator-only controls · Recent 150 orders maximum</div></div><a class="button" href="/cnrbyte/admin/dashboard.pge.php">Admin dashboard</a></header>
<?php if ($message !== ''): ?><div class="notice <?= h($messageType) ?>"><?= h($message) ?></div><?php endif; ?>
<section class="panel"><h2 class="subhead">Find orders</h2><form class="filters" method="get"><input type="text" name="q" value="<?= h($search) ?>" placeholder="Order reference, buyer ID, vendor ID"><select name="status"><option value="">All statuses</option><?php foreach (['paid','cancelled','pending_settlement','settled','refunded'] as $status): ?><option value="<?= h($status) ?>" <?= $statusFilter===$status?'selected':'' ?>><?= h(ucwords(str_replace('_',' ',$status))) ?></option><?php endforeach; ?></select><button class="button" type="submit">Search</button><a class="button" href="marketplace_lifecycle.pge.php">Clear</a></form></section>
<section class="panel"><h2 class="subhead">Order actions</h2><p class="warning">Refunds credit an internal CnrByte account; they do not refund an external card/bank payment. Settlement records must only be submitted after an external payout has actually been completed. Test in staging first.</p>
<div class="table-wrap"><table><thead><tr><th>Order</th><th>Buyer / Vendor</th><th>Order status</th><th>Accounting</th><th>Amounts</th><th>Refund / Cancel</th><th>Record settlement</th></tr></thead><tbody>
<?php if (!$orders): ?><tr><td colspan="7">No orders matched the filters.</td></tr><?php endif; ?>
<?php foreach ($orders as $o): $eligible = strtolower((string)$o['order_status'])==='paid' && strtolower((string)$o['package_status'])==='pending_packaging' && trim((string)($o['tracking_code']??''))==='' && (int)$o['refund_count']===0; $settleEligible = strtolower((string)($o['entry_status']??''))==='pending_settlement' && strtolower((string)$o['order_status'])!=='cancelled' && (int)$o['settlement_count']===0; ?>
<tr><td><strong><?= h((string)$o['order_ref']) ?></strong><div class="muted">ID <?= (int)$o['id'] ?> · <?= h((string)$o['created_at']) ?></div><div class="muted"><?= h((string)$o['package_status']) ?><?= trim((string)$o['tracking_code'])!==''?' · Tracking: '.h((string)$o['tracking_code']):'' ?></div></td>
<td>Buyer: <?= (int)$o['buyer_id'] ?><br>Vendor: <?= (int)$o['vendor_id'] ?></td><td><span class="pill"><?= h((string)$o['order_status']) ?></span></td><td><?= h((string)($o['entry_status']??'missing accounting')) ?></td><td>Total: <strong><?= money($o['total_amount']) ?></strong><br>Vendor payable: <?= money($o['vendor_payable']??0) ?></td>
<td><?php if ($eligible): ?><form class="action-form" method="post" onsubmit="return confirm('Confirm cancellation and internal balance credit? This does not return money to a bank/card.');"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="refund"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="text" name="reason" maxlength="255" placeholder="Reason (optional)"><button class="button" type="submit">Cancel & internal refund</button></form><?php else: ?><span class="muted"><?= (int)$o['refund_count']>0?'Refund already recorded':'Not eligible under current rules' ?></span><?php endif; ?></td>
<td><?php if ($settleEligible): ?><form class="action-form" method="post" onsubmit="return confirm('Confirm the external payout has already been completed? This only records the payout.');"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="settlement"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="text" name="payment_reference" maxlength="120" required placeholder="External payment reference"><button class="button" type="submit">Record payout</button></form><?php else: ?><span class="muted"><?= (int)$o['settlement_count']>0?'Settlement recorded':'Not eligible for settlement' ?></span><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="panel"><h2 class="subhead">Vendor reconciliation summary</h2><div class="table-wrap"><table><thead><tr><th>Vendor ID</th><th>Pending payable</th><th>Settled payable</th><th>Recorded payouts</th><th>Payout difference</th><th>Refunded gross sales</th><th>Orders missing accounting</th></tr></thead><tbody>
<?php if (!$reconciliations): ?><tr><td colspan="7">No vendor reconciliation data available for the current result set.</td></tr><?php endif; ?>
<?php foreach ($reconciliations as $r): ?><tr><td><?= (int)$r['vendor_id'] ?></td><td><?= money($r['pending_payable']) ?></td><td><?= money($r['settled_payable']) ?></td><td><?= money($r['payout_records']) ?></td><td><strong><?= money($r['payout_difference']) ?></strong></td><td><?= money($r['refunded_sales']) ?></td><td><?= (int)$r['orders_missing_accounting'] ?></td></tr><?php endforeach; ?></tbody></table></div><p class="muted">This is a quick summary for vendors present in the filtered order list, not a complete all-vendor audit. Investigate non-zero differences and missing accounting records.</p></section>
</main></body></html>
