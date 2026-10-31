<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();

$rank = strtolower((string)($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? $_SESSION['rank'] ?? ''));
if (!in_array($rank, ['admin', 'administrator'], true)) {
    http_response_code(403);
    exit('Administrator access required.');
}

require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/admin.dbh.php';

$vendorId = filter_var($_POST['vendor_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($vendorId === false || $vendorId === null) {
    header('Location: /cnrbyte/admin/dashboard.pge.php?vendor_approval=invalid');
    exit;
}
$adminId = (int)($_SESSION['user_info']['id'] ?? $_SESSION['user_id'] ?? 0);
$adminName = (string)($_SESSION['user_info']['username'] ?? $_SESSION['user_username'] ?? $_SESSION['username'] ?? 'Administrator');
if ($adminId < 1) {
    http_response_code(403);
    exit('Administrator session is incomplete. Please sign in again.');
}

try {
    $success = approve_vendor($conn, (int)$vendorId, $adminId, $adminName);
} catch (Throwable $e) {
    error_log('CnrByte vendor approval failed: ' . $e->getMessage());
    $success = false;
}
header('Location: /cnrbyte/admin/dashboard.pge.php?vendor_approval=' . ($success ? 'approved' : 'failed'));
exit;
