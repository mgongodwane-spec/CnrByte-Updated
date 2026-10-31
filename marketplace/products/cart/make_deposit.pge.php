<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/global.shw.php';
require_once __DIR__ . '/cart.shw.php';

$_SESSION['current_dashboard'] = 'marketplace_dashboard';
$_SESSION['selected_pge'] = 'dashboard_pge';

cart_bootstrap();

$checkout = cart_get_checkout_context($conn);
$shortfall = isset($_SESSION['cart_payment_shortfall']) ? (float)$_SESSION['cart_payment_shortfall'] : (float)$checkout['shortfall'];
unset($_SESSION['cart_payment_shortfall']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNRBYTE - Make Deposit</title>
    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/main.css">
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <?php  ?>
    <main style="max-width:900px;margin:0 auto;padding:20px;">
        <h2 style="color:#034e22;">Make Deposit</h2>
        <?php render_cart_notice(); ?>

        <div style="background:#fff;padding:20px;border-radius:14px;margin-bottom:18px;">
            <p style="margin:6px 0;">Available Terminal Balance: <strong><?= htmlspecialchars(cart_format_currency((float)$checkout['terminal_balance']), ENT_QUOTES, 'UTF-8') ?></strong></p>
            <p style="margin:6px 0;">Available Card Balance: <strong><?= htmlspecialchars(cart_format_currency((float)$checkout['card_balance']), ENT_QUOTES, 'UTF-8') ?></strong></p>
            <p style="margin:6px 0;">Cart Total: <strong><?= htmlspecialchars(cart_format_currency((float)$checkout['total']), ENT_QUOTES, 'UTF-8') ?></strong></p>
            <p style="margin:6px 0;color:#9b1c1c;">Shortfall: <strong><?= htmlspecialchars(cart_format_currency($shortfall), ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>

        <div style="background:#fff;padding:20px;border-radius:14px;margin-bottom:18px;">
            <h3 style="margin-top:0;">Next Step</h3>
            <p style="margin:0 0 12px;">The user does not have enough terminal funds to complete this order. Deposit the shortfall, then return to confirm payment, or go back and choose card payment instead.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="/cnrbyte/marketplace/products/cart/edit.pge.php" class="form-btn" style="text-decoration:none;">Back To Confirm Payment</a>
                <form action="/cnrbyte/marketplace/products/cart/poke_admin.run.php" method="post" style="margin:0;">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" name="poke_admin" class="form-btn">Notify Admin About Deposit</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
