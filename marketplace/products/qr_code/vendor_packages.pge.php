<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/global.shw.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once __DIR__ . '/package_helpers.php';

$_SESSION['current_dashboard'] = 'marketplace_dashboard';
$_SESSION['selected_pge'] = 'dashboard_pge';

$vendorUserId = (int)($_SESSION['user_info']['id'] ?? 0);
$selectedVendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
$orders = ($vendorUserId > 0 && $selectedVendorId > 0) ? qr_get_vendor_orders($conn, $vendorUserId, $selectedVendorId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNRBYTE - QR Packaging</title>
    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/main.css">
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <?php  ?>
    <main style="max-width:1100px;margin:0 auto;padding:20px;">
        <h2 style="color:#034e22;">QR Packaging Queue</h2>
        <p style="color:#4b5563;">Package paid orders, generate a trackable QR code, and notify the buyer with the tracking link.</p>

        <?php if ($orders === []): ?>
            <div style="background:#fff;padding:20px;border-radius:14px;">
                <p>No vendor orders are waiting in the QR packaging queue.</p>
            </div>
        <?php else: ?>
            <div style="display:grid;gap:18px;">
                <?php foreach ($orders as $order): ?>
                    <?php $items = qr_get_order_items($conn, (int)$order['id']); ?>
                    <div style="background:#fff;padding:20px;border-radius:14px;">
                        <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                            <div>
                                <h3 style="margin:0 0 8px;"><?= htmlspecialchars((string)$order['order_ref'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p style="margin:4px 0;"><strong>Buyer ID:</strong> <?= (int)$order['buyer_id'] ?></p>
                                <p style="margin:4px 0;"><strong>Vendor ID:</strong> <?= (int)$order['vendor_id'] ?></p>
                                <p style="margin:4px 0;"><strong>Status:</strong> <?= htmlspecialchars((string)$order['package_status'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p style="margin:4px 0;"><strong>Payment Method:</strong> <?= htmlspecialchars((string)($order['payment_method'] ?? 'terminal'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p style="margin:4px 0;"><strong>Delivery:</strong> <?= htmlspecialchars(trim(implode(', ', array_filter([(string)($order['delivery_street'] ?? ''), (string)($order['delivery_suburb'] ?? ''), (string)($order['delivery_town'] ?? ''), (string)($order['delivery_province'] ?? ''), (string)($order['delivery_postal_code'] ?? '')]))), ENT_QUOTES, 'UTF-8') ?></p>
                                <p style="margin:4px 0;"><strong>Total:</strong> R<?= number_format((float)$order['total_amount'], 2) ?></p>
                            </div>
                            <div style="text-align:right;">
                                <?php if (!empty($order['qr_code_img'])): ?>
                                    <img src="/cnrbyte/uploads/products/qr_codes/<?= htmlspecialchars((string)$order['qr_code_img'], ENT_QUOTES, 'UTF-8') ?>" alt="Tracking QR" style="width:120px;height:120px;object-fit:contain;">
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="margin-top:14px;">
                            <?php foreach ($items as $item): ?>
                                <p style="margin:6px 0; color:#4b5563;"><?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?> x<?= (int)$item['quantity'] ?></p>
                            <?php endforeach; ?>
                        </div>

                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px;">
                            <?php if (($order['package_status'] ?? '') !== 'packaged'): ?>
                                <form action="/cnrbyte/marketplace/products/qr_code/package_order.run.php" method="post" style="margin:0;">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <button type="submit" name="package_order" class="form-btn">Package And Generate QR</button>
                                </form>
                            <?php else: ?>
                                <a href="/cnrbyte/marketplace/products/qr_code/track_parcel.php?tracking=<?= urlencode((string)$order['tracking_code']) ?>" class="form-btn" style="text-decoration:none;">Open Tracking</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
