<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/global.shw.php';
require_once __DIR__ . '/cart.shw.php';

$_SESSION['current_dashboard'] = 'marketplace_dashboard';
$_SESSION['selected_pge'] = 'edit_pge';

cart_bootstrap();

$items = cart_get_items($conn);
$total = cart_get_total($conn);
$checkout = cart_get_checkout_context($conn);
$addressLines = $checkout['address'] ? cart_format_address_lines($checkout['address']) : [];
$shortfall = isset($_SESSION['cart_payment_shortfall']) ? (float)$_SESSION['cart_payment_shortfall'] : (float)$checkout['shortfall'];
unset($_SESSION['cart_payment_shortfall']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNRBYTE - Edit Cart Checkout</title>
    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/main.css">
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <?php  ?>
    <main style="max-width:900px;margin:0 auto;padding:20px;">
        <h2 style="color:#034e22;">Edit Cart Checkout</h2>
        <?php render_cart_notice(); ?>

        <?php if ($items === []): ?>
            <div style="background:#fff;padding:20px;border-radius:14px;">
                <p>Your cart is empty.</p>
                <a href="/cnrbyte/marketplace/products/cart/dashboard.pge.php" class="form-btn" style="text-decoration:none;">Back To Cart</a>
            </div>
        <?php else: ?>
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
                        <p style="margin:0 0 12px;color:#9b1c1c;">A delivery address must be confirmed before payment.</p>
                    <?php endif; ?>
                </section>

                <section style="background:#fff;padding:20px;border-radius:14px;">
                    <h3 style="margin-top:0;">Terminal Funds</h3>
                    <p style="margin:6px 0;">Available Balance: <strong><?= htmlspecialchars(cart_format_currency((float)$checkout['terminal_balance']), ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p style="margin:6px 0;">Card Balance: <strong><?= htmlspecialchars(cart_format_currency((float)$checkout['card_balance']), ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p style="margin:6px 0;">Order Total: <strong><?= htmlspecialchars(cart_format_currency($total), ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <?php if ($checkout['has_terminal_funds']): ?>
                        <p style="margin:12px 0 0;color:#166534;">This order can be paid from the terminal balance.</p>
                    <?php else: ?>
                        <p style="margin:12px 0 0;color:#9b1c1c;">Shortfall: <?= htmlspecialchars(cart_format_currency($shortfall), ENT_QUOTES, 'UTF-8') ?></p>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px;">
                            <a href="/cnrbyte/marketplace/products/cart/make_deposit.pge.php" class="form-btn" style="text-decoration:none;">Make Deposit</a>
                        </div>
                    <?php endif; ?>
                    <?php if ($checkout['has_card_funds']): ?>
                        <p style="margin:12px 0 0;color:#166534;">The linked card account can also pay for this order.</p>
                    <?php else: ?>
                        <p style="margin:12px 0 0;color:#9b1c1c;">The linked card account does not have enough available balance.</p>
                    <?php endif; ?>
                </section>
            </div>

            <div style="background:#fff;padding:20px;border-radius:14px;margin-bottom:18px;">
                <h3 style="margin-top:0;">Confirm Or Update Address</h3>
                <p style="margin-top:0;color:#4b5563;">Use your saved profile address, or set a temporary delivery address just for this checkout.</p>
                <form action="/cnrbyte/marketplace/products/cart/set_temporary_address.run.php" method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="text" name="street" placeholder="Street or stand number" value="<?= htmlspecialchars((string)($checkout['address']['street'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                    <input type="text" name="suburb" placeholder="Suburb" value="<?= htmlspecialchars((string)($checkout['address']['suburb'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                    <input type="text" name="town" placeholder="Town" value="<?= htmlspecialchars((string)($checkout['address']['town'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                    <input type="text" name="province" placeholder="Province" value="<?= htmlspecialchars((string)($checkout['address']['province'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                    <input type="text" name="postal_code" placeholder="Postal code" value="<?= htmlspecialchars((string)($checkout['address']['postal_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                    <div style="display:flex;align-items:center;">
                        <button type="submit" class="form-btn">Confirm Address</button>
                    </div>
                </form>
            </div>

            <div style="background:#fff;padding:20px;border-radius:14px;margin-bottom:18px;">
                <p style="margin-top:0;">When you confirm, the cart will only complete if a delivery address exists. Terminal payments also require enough balance; if not, the user must deposit or switch to card payment.</p>
                <p style="font-weight:700;margin-bottom:0;">Amount to confirm: <?= htmlspecialchars(cart_format_currency($total), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="/cnrbyte/marketplace/products/cart/create.pge.php" class="form-btn" style="text-decoration:none;">Review Order</a>
                <form action="/cnrbyte/marketplace/products/cart/create.run.php" method="post" style="margin:0;">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="payment_method" value="terminal">
                    <button type="submit" name="confirm_checkout" class="form-btn" <?= (!$checkout['has_address'] || !$checkout['has_terminal_funds']) ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : '' ?>>Pay From Terminal</button>
                </form>
                <form action="/cnrbyte/marketplace/products/cart/create.run.php" method="post" style="margin:0;">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="payment_method" value="card">
                    <button type="submit" name="confirm_checkout" class="form-btn" <?= (!$checkout['has_address'] || !$checkout['has_card_funds']) ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : '' ?>>Use Card Payment</button>
                </form>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
