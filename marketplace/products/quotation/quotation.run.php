<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

$clientId = (int)($_SESSION['user_info']['id'] ?? 0);
$rank = cnrbyte_auth_normalize_rank($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? 'client');
if ($clientId < 1 || $rank !== 'client') {
    http_response_code(403);
    exit('A signed-in client account is required to create a quotation.');
}
if (!isset($_POST['save_quote'])) {
    http_response_code(400);
    exit('Unsupported quotation action.');
}

$quoteName = trim((string)($_POST['quote_name'] ?? ''));
$productIds = $_POST['product_ids'] ?? null;
$quantities = $_POST['qtys'] ?? null;
if ($quoteName === '' || mb_strlen($quoteName) > 255 || !is_array($productIds) || !is_array($quantities) || count($productIds) < 1 || count($productIds) > 50 || count($productIds) !== count($quantities)) {
    header('Location: /cnrbyte/marketplace/products/quotation/quotation.pge.php?error=invalid');
    exit;
}

$items = [];
$total = 0.0;
try {
    $conn->begin_transaction();
    $productStmt = $conn->prepare("SELECT p.sale_price FROM products p INNER JOIN vendors v ON v.id=p.vendor_id AND v.status='approved' WHERE p.id=? LIMIT 1");
    if (!$productStmt) throw new RuntimeException('Could not prepare product price lookup.');
    foreach (array_values($productIds) as $index => $rawId) {
        $productId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $rawQty = $quantities[$index] ?? null;
        if ($productId === false || $productId === null || !is_scalar($rawQty) || !is_numeric((string)$rawQty)) {
            throw new InvalidArgumentException('Each quotation item needs a valid product and quantity.');
        }
        $qty = (float)$rawQty;
        if (!is_finite($qty) || $qty <= 0 || $qty > 100000) throw new InvalidArgumentException('Quantities must be greater than zero and no more than 100,000.');
        $productId = (int)$productId;
        $productStmt->bind_param('i', $productId);
        if (!$productStmt->execute()) throw new RuntimeException('Could not read product price.');
        $product = $productStmt->get_result()->fetch_assoc();
        if (!$product) throw new InvalidArgumentException('One of the selected products is unavailable.');
        $price = round((float)$product['sale_price'], 2);
        if (!is_finite($price) || $price < 0) throw new RuntimeException('A product has an invalid sale price.');
        $subtotal = round($qty * $price, 2);
        $total = round($total + $subtotal, 2);
        $items[] = [$productId, $qty, $price, $subtotal];
    }
    $productStmt->close();

    $header = $conn->prepare('INSERT INTO quotations (client_id, quote_name, grand_total) VALUES (?, ?, ?)');
    if (!$header) throw new RuntimeException('Could not prepare quotation header.');
    $header->bind_param('isd', $clientId, $quoteName, $total);
    if (!$header->execute()) throw new RuntimeException('Could not save quotation header.');
    $quoteId = (int)$header->insert_id;
    $header->close();

    $itemStmt = $conn->prepare('INSERT INTO quotation_items (quote_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)');
    if (!$itemStmt) throw new RuntimeException('Could not prepare quotation items.');
    foreach ($items as [$productId, $qty, $price, $subtotal]) {
        $itemStmt->bind_param('iiddd', $quoteId, $productId, $qty, $price, $subtotal);
        if (!$itemStmt->execute()) throw new RuntimeException('Could not save a quotation item.');
    }
    $itemStmt->close();
    $conn->commit();
    header('Location: /cnrbyte/marketplace/products/quotation/quotation.pge.php?success=1');
    exit;
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('CnrByte quotation save failed: ' . $e->getMessage());
    header('Location: /cnrbyte/marketplace/products/quotation/quotation.pge.php?error=save');
    exit;
}
