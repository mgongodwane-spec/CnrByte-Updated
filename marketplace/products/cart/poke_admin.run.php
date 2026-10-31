<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once __DIR__ . '/../../../includes/conn.dbh.php';
require_once __DIR__ . '/../../../messages/notifications.php';
require_once __DIR__ . '/cart.func.php';

cart_bootstrap();

$senderId = (int)($_SESSION['user_info']['id'] ?? 0);
$receiverId = (int)($_SESSION['admin_id'] ?? 0);

if (!isset($_POST['poke_admin']) || $senderId <= 0) {
    cart_set_notice('error', 'Invalid deposit notification request.');
    header('Location: /cnrbyte/marketplace/products/cart/make_deposit.pge.php');
    exit;
}

if ($receiverId <= 0) {
    cart_set_notice('error', 'No admin account is configured for deposit notifications.');
    header('Location: /cnrbyte/marketplace/products/cart/make_deposit.pge.php');
    exit;
}

$message = 'A deposit request has been submitted for marketplace checkout funding. Please review and confirm the payment.';
notifications_send($conn, $senderId, $receiverId, 'Deposit Request', $message);
cart_set_notice('success', 'Deposit request sent to admin.');

header('Location: /cnrbyte/marketplace/products/cart/make_deposit.pge.php');
exit;
