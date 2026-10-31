<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once __DIR__ . '/../../../includes/conn.dbh.php';
require_once __DIR__ . '/cart.func.php';

cart_bootstrap();

if (!isset($_POST['confirm_checkout'])) {
    cart_set_notice('error', 'Invalid checkout request.');
    header('Location: /cnrbyte/marketplace/products/cart/edit.pge.php');
    exit;
}

$paymentMethod = (string)($_POST['payment_method'] ?? 'terminal');
if ($paymentMethod !== 'card' && $paymentMethod !== 'terminal') {
    $paymentMethod = 'terminal';
}

$result = cart_checkout($conn, $paymentMethod);

cart_set_notice($result['success'] ? 'success' : 'error', $result['message']);

$redirect = '/cnrbyte/marketplace/products/cart/edit.pge.php';
if ($result['success']) {
    $redirect = '/cnrbyte/marketplace/products/cart/dashboard.pge.php';
}

header('Location: ' . $redirect);
exit;
