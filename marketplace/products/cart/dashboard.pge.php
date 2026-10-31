<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/global.shw.php';
require_once __DIR__ . '/cart.shw.php';

$_SESSION['current_dashboard'] = 'marketplace_dashboard';
$_SESSION['selected_pge'] = 'dashboard_pge';

cart_bootstrap();

$cartCount = cart_count_items();
$cartTotal = cart_get_total($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNRBYTE - Cart Dashboard</title>
    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/main.css">
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <?php  ?>

    <main style="max-width:1100px;margin:0 auto;padding:20px;">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:20px;">
            <div>
                <h2 style="margin:0 0 8px;color:#034e22;">Cart Dashboard</h2>
                <p style="margin:0;color:#4b5563;">Review, update, and prepare marketplace items before checkout.</p>
            </div>
            <div style="background:#fff;padding:16px 18px;border-radius:14px;min-width:240px;">
                <p style="margin:0;color:#6b7280;">Items</p>
                <h3 style="margin:6px 0 12px;"><?= (int)$cartCount ?></h3>
                <p style="margin:0;color:#6b7280;">Total</p>
                <h3 style="margin:6px 0 0;"><?= htmlspecialchars(cart_format_currency($cartTotal), ENT_QUOTES, 'UTF-8') ?></h3>
            </div>
        </div>

        <?php render_cart_notice(); ?>

        <?php render_cart_items($conn); ?>

        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px;">
            <a href="/cnrbyte/marketplace/dashboard.pge.php" class="form-btn" style="text-decoration:none;">Continue Shopping</a>
            <?php if ($cartCount > 0): ?>
                <a href="/cnrbyte/marketplace/products/cart/create.pge.php" class="form-btn" style="text-decoration:none;">Checkout</a>
                <form action="/cnrbyte/marketplace/products/cart/edit.run.php" method="post" style="margin:0;">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="clear">
                    <input type="hidden" name="redirect" value="/cnrbyte/marketplace/products/cart/dashboard.pge.php">
                    <button type="submit" class="form-btn" style="background:#991b1b;">Clear Cart</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
