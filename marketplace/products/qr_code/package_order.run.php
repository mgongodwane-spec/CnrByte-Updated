<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once __DIR__ . '/package_helpers.php';

$vendorUserId = (int)($_SESSION['user_info']['id'] ?? 0);
$orderId = (int)($_POST['order_id'] ?? 0);

if (!isset($_POST['package_order']) || $vendorUserId <= 0 || $orderId <= 0) {
    header('Location: /cnrbyte/marketplace/products/qr_code/vendor_packages.pge.php');
    exit;
}

$selectedVendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
$order = qr_package_order($conn, $orderId, $vendorUserId, $selectedVendorId);
if ($order) {
    qr_send_packaged_message($conn, $order);
}

header('Location: /cnrbyte/marketplace/products/qr_code/vendor_packages.pge.php');
exit;
