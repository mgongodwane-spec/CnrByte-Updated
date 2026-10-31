<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once __DIR__ . '/../../../includes/conn.dbh.php';
require_once __DIR__ . '/cart.func.php';

cart_bootstrap();

$street = trim((string)($_POST['street'] ?? $_POST['stand_no'] ?? ''));
$suburb = trim((string)($_POST['suburb'] ?? ''));
$town = trim((string)($_POST['town'] ?? ''));
$province = trim((string)($_POST['province'] ?? ''));
$postalCode = trim((string)($_POST['postal_code'] ?? ''));

if ($street === '' || $suburb === '' || $town === '' || $postalCode === '') {
    cart_set_notice('error', 'Fill in the delivery address before continuing.');
    header('Location: /cnrbyte/marketplace/products/cart/edit.pge.php');
    exit;
}

cart_set_temporary_address($street, $suburb, $town, $postalCode, $province);
cart_set_notice('success', 'Delivery address confirmed for this checkout.');

header('Location: /cnrbyte/marketplace/products/cart/edit.pge.php');
exit;
