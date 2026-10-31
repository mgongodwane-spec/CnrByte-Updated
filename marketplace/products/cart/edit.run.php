<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once __DIR__ . '/../../../includes/conn.dbh.php';
require_once __DIR__ . '/cart.func.php';

cart_bootstrap();

$action = (string)($_POST['action'] ?? '');
$productId = (int)($_POST['product_id'] ?? $_POST['item_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$redirect = cart_resolve_redirect($_POST['redirect'] ?? '/cnrbyte/marketplace/products/cart/dashboard.pge.php');

if ($action === 'clear') {
    cart_clear_items();
    cart_set_notice('success', 'Your cart has been cleared.');
    header('Location: ' . $redirect);
    exit;
}

if ($productId <= 0) {
    cart_set_notice('error', 'Invalid cart action.');
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'update') {
    if ($quantity <= 0) {
        cart_remove_item($productId);
        cart_set_notice('success', 'Item removed from cart.');
    } else {
        cart_update_item($productId, $quantity);
        cart_set_notice('success', 'Cart quantity updated.');
    }
} elseif ($action === 'remove') {
    cart_remove_item($productId);
    cart_set_notice('success', 'Item removed from cart.');
} else {
    cart_set_notice('error', 'Unknown cart action.');
}

header('Location: ' . $redirect);
exit;
