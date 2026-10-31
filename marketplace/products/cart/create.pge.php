<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/global.shw.php';
require_once __DIR__ . '/cart.shw.php';

$_SESSION['current_dashboard'] = 'marketplace_dashboard';
$_SESSION['selected_pge'] = 'create_pge';

cart_bootstrap();

$items = cart_get_items($conn);
$total = cart_get_total($conn);
$checkout = cart_get_checkout_context($conn);
$addressLines = $checkout['address'] ? cart_format_address_lines($checkout['address']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNRBYTE - Create Cart Order</title>
    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/main.css">
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <?php  ?>
    <main style="max-width:1000px;margin:0 auto;padding:20px;">
        <h2 style="color:#034e22;">Create Cart Order</h2>
        <?php render_cart_notice(); ?>

        <?php if ($items === []): ?>
            <div style="background:#fff;padding:20px;border-radius:14px;">
                <p>Your cart is empty.</p>
                <a href="/cnrbyte/marketplace/dashboard.pge.php" class="form-btn" style="text-decoration:none;">Go To Marketplace</a>
            </div>
        <?php else: ?>
            <div style="background:#fff;padding:20px;border-radius:14px;margin-bottom:18px;">
                <h3 style="margin-top:0;">Order Summary</h3>
                <?php foreach ($items as $item): ?>
                    <p style="margin:8px 0;">
                        <strong><?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        |
                        Vendor: <?= htmlspecialchars((string)$item['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                        |
                        Qty: <?= (int)$item['quantity'] ?>
                        |
                        Total: <?= htmlspecialchars(cart_format_currency((float)$item['line_total']), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endforeach; ?>
                <p style="margin:16px 0 0;font-weight:700;">Grand Total: <?= htmlspecialchars(cart_format_currency($total), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:18px;">
                <section style="background:#fff;padding:20px;border-radius:14px;">
                    <h3 style="margin-top:0;">Delivery Address</h3>
                    <?php if ($addressLines !== []): ?>
                        <?php foreach ($addressLines as $line): ?>
                            <p style="margin:6px 0;"><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endforeach; ?>
                        <p style="margin:12px 0 0;color:#4b5563;font-size:0.92rem;">
                            Source: <?= $checkout['address']['source'] === 'temporary' ? 'Temporary checkout address' : 'Saved profile address' ?>
                        </p>
                    <?php else: ?>
                        <p style="margin:0;color:#9b1c1c;">No confirmed delivery address yet.</p>
                    <?php endif; ?>
                </section>

                <section style="background:#fff;padding:20px;border-radius:14px;">
                    <h3 style="margin-top:0;">Payment Readiness</h3>
                    <p style="margin:6px 0;">Terminal Balance: <strong><?= htmlspecialchars(cart_format_currency((float)$checkout['terminal_balance']), ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p style="margin:6px 0;">Card Balance: <strong><?= htmlspecialchars(cart_format_currency((float)$checkout['card_balance']), ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p style="margin:6px 0;">Order Total: <strong><?= htmlspecialchars(cart_format_currency((float)$checkout['total']), ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <?php if ($checkout['has_terminal_funds']): ?>
                        <p style="margin:12px 0 0;color:#166534;">Terminal funds are enough for this order.</p>
                    <?php else: ?>
                        <p style="margin:12px 0 0;color:#9b1c1c;">Shortfall: <?= htmlspecialchars(cart_format_currency((float)$checkout['shortfall']), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($checkout['has_card_funds']): ?>
                        <p style="margin:12px 0 0;color:#166534;">A linked card account can also cover this order.</p>
                    <?php else: ?>
                        <p style="margin:12px 0 0;color:#9b1c1c;">Linked card balance is not enough for this order.</p>
                    <?php endif; ?>
                </section>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="/cnrbyte/marketplace/products/cart/dashboard.pge.php" class="form-btn" style="text-decoration:none;">Back To Cart</a>
                <a href="/cnrbyte/marketplace/products/cart/edit.pge.php" class="form-btn" style="text-decoration:none;">Confirm Payment</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
