<?php
declare(strict_types=1);

require_once __DIR__ . '/phpqrcode/qrlib.php';
require_once __DIR__ . '/../includes/marketplace_orders.php';
require_once __DIR__ . '/../includes/notifications.php';

function qr_ensure_fulfillment_tables(mysqli $conn): void
{
    marketplace_orders_ensure_tables($conn);
}

function qr_generate_tracking_code(string $orderRef): string
{
    try {
        $random = strtoupper(bin2hex(random_bytes(4)));
    } catch (Throwable $throwable) {
        $random = strtoupper(substr(md5((string)microtime(true)), 0, 8));
    }

    return 'TRK-' . preg_replace('/[^A-Z0-9]/', '', strtoupper($orderRef)) . '-' . $random;
}

function qr_build_tracking_link(string $trackingCode): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . $host . '/cnrbyte/marketplace/products/qr_code/track_parcel.php?tracking=' . urlencode($trackingCode);
}

function qr_generate_package_qr(string $trackingCode): string
{
    $qrFilename = 'shipment_' . preg_replace('/[^A-Z0-9_-]/i', '_', $trackingCode) . '.png';
    $saveDir = $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/uploads/products/qr_codes/';
    $savePath = $saveDir . $qrFilename;

    if (!is_dir($saveDir)) {
        mkdir($saveDir, 0777, true);
    }

    QRcode::png(qr_build_tracking_link($trackingCode), $savePath, QR_ECLEVEL_L, 10);

    return $qrFilename;
}

function qr_get_vendor_orders(mysqli $conn, int $vendorUserId, int $vendorId = 0): array
{
    qr_ensure_fulfillment_tables($conn);

    $stmt = $conn->prepare(
        "SELECT *
         FROM marketplace_orders
         WHERE vendor_user_id = ?
           AND (? <= 0 OR vendor_id = ?)
           AND order_status = 'paid'
         ORDER BY created_at DESC"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('iii', $vendorUserId, $vendorId, $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();

    return $orders;
}

function qr_get_order_items(mysqli $conn, int $orderId): array
{
    $stmt = $conn->prepare(
        "SELECT product_id, product_name, quantity, unit_price, line_total
         FROM marketplace_order_items
         WHERE order_id = ?
         ORDER BY id ASC"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    return $items;
}

function qr_package_order(mysqli $conn, int $orderId, int $vendorUserId, int $vendorId = 0): ?array
{
    qr_ensure_fulfillment_tables($conn);

    $stmt = $conn->prepare(
        "SELECT *
         FROM marketplace_orders
         WHERE id = ? AND vendor_user_id = ? AND (? <= 0 OR vendor_id = ?)
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iiii', $orderId, $vendorUserId, $vendorId, $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$order) {
        return null;
    }

    if (!empty($order['tracking_code']) && !empty($order['qr_code_img'])) {
        return $order;
    }

    $trackingCode = qr_generate_tracking_code((string)$order['order_ref']);
    $qrImage = qr_generate_package_qr($trackingCode);

    $update = $conn->prepare(
        "UPDATE marketplace_orders
         SET tracking_code = ?, qr_code_img = ?, package_status = 'packaged', packaged_at = NOW()
         WHERE id = ? AND vendor_id = ? AND vendor_user_id = ?"
    );
    if (!$update) {
        return null;
    }

    $update->bind_param('ssiii', $trackingCode, $qrImage, $orderId, $vendorId, $vendorUserId);
    $update->execute();
    $update->close();

    $order['tracking_code'] = $trackingCode;
    $order['qr_code_img'] = $qrImage;
    $order['package_status'] = 'packaged';
    $order['packaged_at'] = date('Y-m-d H:i:s');

    return $order;
}

function qr_send_packaged_message(mysqli $conn, array $order): void
{
    $vendorUserId = (int)($order['vendor_user_id'] ?? 0);
    $buyerId = (int)($order['buyer_id'] ?? 0);
    $trackingCode = (string)($order['tracking_code'] ?? '');
    $orderRef = (string)($order['order_ref'] ?? '');

    if ($vendorUserId <= 0 || $buyerId <= 0 || $trackingCode === '') {
        return;
    }

    $trackingLink = qr_build_tracking_link($trackingCode);
    $message = "Order {$orderRef} has been packaged. Tracking code: {$trackingCode}. Track here: {$trackingLink}";
    notifications_send($conn, $vendorUserId, $buyerId, 'Marketplace Order Packaged', $message);
}
