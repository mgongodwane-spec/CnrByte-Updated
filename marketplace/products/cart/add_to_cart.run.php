<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once __DIR__ . '/../../../includes/conn.dbh.php';
require_once __DIR__ . '/cart.func.php';

cart_bootstrap();

$productId = (int)($_POST['product_id'] ?? $_POST['item_id'] ?? $_GET['product_id'] ?? $_GET['item_id'] ?? 0);
$quantity = cart_normalize_quantity($_POST['quantity'] ?? $_POST['units'] ?? $_GET['quantity'] ?? 1);
$redirect = cart_resolve_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? ''), '/cnrbyte/marketplace/dashboard.pge.php');

if ($productId <= 0) {
    cart_set_notice('error', 'We could not identify the product to add to cart.');
    header('Location: ' . $redirect);
    exit;
}

if (!cart_add_item($conn, $productId, $quantity)) {
    cart_set_notice('error', 'That product is no longer available.');
    header('Location: ' . $redirect);
    exit;
}

$product = cart_find_product($conn, $productId);
$productName = $product['product_name'] ?? 'Product';
cart_set_notice('success', $productName . ' added to cart.');

header('Location: ' . $redirect);
exit;
