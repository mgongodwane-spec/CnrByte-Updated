<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/package_helpers.php';

qr_ensure_fulfillment_tables($conn);

$trackingCode = trim((string)($_GET['tracking'] ?? ''));
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$shipment = null;
$shipmentItems = [];
$product = null;

if ($trackingCode !== '') {
    $stmt = $conn->prepare(
        "SELECT *
         FROM marketplace_orders
         WHERE tracking_code = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('s', $trackingCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $shipment = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }

    if ($shipment) {
        $shipmentItems = qr_get_order_items($conn, (int)$shipment['id']);
    }
} elseif ($productId > 0) {
    $stmt = $conn->prepare(
        "SELECT p.*, COALESCE(v.reg_name, 'Unknown Vendor') AS vendor_name
         FROM products p
         LEFT JOIN vendors v ON p.vendor_id = v.id
         WHERE p.id = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNRBYTE Tracking</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f5f7;
            padding: 16px;
        }

        .tracking-card {
            background: #fff;
            max-width: 760px;
            margin: 20px auto;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.09);
        }

        .tracking-header {
            background: #034e22;
            color: #fff;
            padding: 24px;
        }

        .tracking-body {
            padding: 24px;
        }

        .section {
            margin-bottom: 22px;
        }

        .section h3 {
            margin: 0 0 12px;
            color: #123524;
        }

        .info-row {
            margin: 8px 0;
            color: #334155;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
        }

        .item-row {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="tracking-card">
        <div class="tracking-header">
            <h2 style="margin:0;">Parcel Tracking</h2>
            <?php if ($shipment): ?>
                <p style="margin:8px 0 0;">Tracking Code: <?= htmlspecialchars((string)$shipment['tracking_code'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($product): ?>
                <p style="margin:8px 0 0;">Product QR Reference: #<?= (int)$product['id'] ?></p>
            <?php else: ?>
                <p style="margin:8px 0 0;">Tracking information not found.</p>
            <?php endif; ?>
        </div>

        <div class="tracking-body">
            <?php if ($shipment): ?>
                <div class="section">
                    <h3>Shipment Status</h3>
                    <p class="info-row"><span class="status-badge"><?= htmlspecialchars((string)$shipment['package_status'], ENT_QUOTES, 'UTF-8') ?></span></p>
                    <p class="info-row"><strong>Order Ref:</strong> <?= htmlspecialchars((string)$shipment['order_ref'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="info-row"><strong>Buyer ID:</strong> <?= (int)$shipment['buyer_id'] ?></p>
                    <p class="info-row"><strong>Vendor ID:</strong> <?= (int)$shipment['vendor_id'] ?></p>
                    <p class="info-row"><strong>Vendor User ID:</strong> <?= (int)$shipment['vendor_user_id'] ?></p>
                    <p class="info-row"><strong>Payment Method:</strong> <?= htmlspecialchars((string)($shipment['payment_method'] ?? 'terminal'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="info-row"><strong>Delivery Address:</strong> <?= htmlspecialchars(trim(implode(', ', array_filter([(string)($shipment['delivery_street'] ?? ''), (string)($shipment['delivery_suburb'] ?? ''), (string)($shipment['delivery_town'] ?? ''), (string)($shipment['delivery_province'] ?? ''), (string)($shipment['delivery_postal_code'] ?? '')]))), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="info-row"><strong>Packaged At:</strong> <?= htmlspecialchars((string)($shipment['packaged_at'] ?? 'Pending packaging'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="info-row"><strong>Total Amount:</strong> R<?= number_format((float)$shipment['total_amount'], 2) ?></p>
                </div>

                <div class="section">
                    <h3>Package Contents</h3>
                    <?php foreach ($shipmentItems as $item): ?>
                        <div class="item-row">
                            <div class="info-row"><strong><?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div class="info-row">Product ID: <?= (int)$item['product_id'] ?> | Quantity: <?= (int)$item['quantity'] ?> | Line Total: R<?= number_format((float)$item['line_total'], 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($product): ?>
                <div class="section">
                    <h3>Product Reference</h3>
                    <p class="info-row"><strong>Product:</strong> <?= htmlspecialchars((string)$product['product_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="info-row"><strong>Vendor:</strong> <?= htmlspecialchars((string)$product['vendor_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="info-row"><strong>Measure:</strong> <?= htmlspecialchars((string)$product['measure'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="info-row"><strong>Description:</strong> <?= htmlspecialchars((string)$product['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php else: ?>
                <div class="section">
                    <h3>Tracking Not Found</h3>
                    <p class="info-row">This parcel tracking code or product QR reference could not be found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
