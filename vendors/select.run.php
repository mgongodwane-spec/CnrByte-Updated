<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['user_info']['id'])) {
    http_response_code(403); exit('Not authorised.');
}
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403); exit('Invalid or expired security token.');
}
$clientId = (int)$_SESSION['user_info']['id'];
$vendorId = filter_var($_POST['vendor_id'] ?? null, FILTER_VALIDATE_INT);
if (!$vendorId || $vendorId < 1) { http_response_code(400); exit('Invalid business selection.'); }
$stmt = $conn->prepare('SELECT id, client_id, reg_name, reg_no, tax_no, status FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1');
$stmt->bind_param('iii', $vendorId, $clientId, $clientId);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$vendor) { http_response_code(403); exit('You do not have access to that business.'); }
$_SESSION['vendor_info'] = [
    'id' => (int)$vendor['id'], 'client_id' => (int)$vendor['client_id'],
    'reg_name' => (string)$vendor['reg_name'], 'reg_no' => (string)($vendor['reg_no'] ?? ''),
    'tax_no' => (string)($vendor['tax_no'] ?? ''), 'status' => (string)($vendor['status'] ?? 'pending'),
];
$_SESSION['current_dashboard'] = 'vendors_dashboard';
cnrbyte_auth_rotate_csrf();
header('Location: /cnrbyte/vendors/dashboard.pge.php');
exit;
