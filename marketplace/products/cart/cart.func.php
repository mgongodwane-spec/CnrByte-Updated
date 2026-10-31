<?php
declare(strict_types=1);

require_once __DIR__ . '/../../marketplace_orders.php';
require_once __DIR__ . '/../../../messages/notifications.php';

function cart_bootstrap(): void
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

function cart_normalize_quantity(mixed $quantity): int
{
    $qty = (int)$quantity;
    return $qty > 0 ? $qty : 1;
}

function cart_set_notice(string $type, string $message): void
{
    $_SESSION['cart_notice'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function cart_pull_notice(): ?array
{
    if (!isset($_SESSION['cart_notice']) || !is_array($_SESSION['cart_notice'])) {
        return null;
    }

    $notice = $_SESSION['cart_notice'];
    unset($_SESSION['cart_notice']);

    return $notice;
}

function cart_resolve_redirect(?string $requestedRedirect, string $fallback = '/cnrbyte/marketplace/products/cart/dashboard.pge.php'): string
{
    $redirect = trim((string)$requestedRedirect);
    if ($redirect === '') {
        return $fallback;
    }

    if (str_starts_with($redirect, '/cnrbyte/')) {
        return $redirect;
    }

    if (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://')) {
        $parts = parse_url($redirect);
        if (($parts['path'] ?? '') !== '') {
            $path = $parts['path'];
            if (isset($parts['query'])) {
                $path .= '?' . $parts['query'];
            }
            if (str_starts_with($path, '/cnrbyte/')) {
                return $path;
            }
        }
    }

    return $fallback;
}

function cart_find_product(mysqli $conn, int $productId): ?array
{
    $query = "
        SELECT p.*, v.reg_name AS vendor_name
        FROM products p
        LEFT JOIN vendors v ON v.id = p.vendor_id
        WHERE p.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $product ?: null;
}

function cart_find_client(mysqli $conn, int $clientId): ?array
{
    $stmt = $conn->prepare("SELECT id, username, email, phone FROM clients WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $client ?: null;
}

function cart_find_client_profile(mysqli $conn, int $clientId): ?array
{
    $stmt = $conn->prepare(
        "SELECT str_address, phy_suburb, phy_town, phy_province, phy_postal_code
         FROM profiles
         WHERE client_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $profile ?: null;
}

function cart_set_temporary_address(string $street, string $suburb, string $town, string $postalCode, string $province = ''): void
{
    $_SESSION['cart_temp_address'] = [
        'street' => trim($street),
        'suburb' => trim($suburb),
        'town' => trim($town),
        'province' => trim($province),
        'postal_code' => trim($postalCode),
    ];
}

function cart_clear_temporary_address(): void
{
    unset($_SESSION['cart_temp_address']);
}

function cart_get_confirmed_address(mysqli $conn, int $clientId): ?array
{
    $temp = $_SESSION['cart_temp_address'] ?? null;
    if (is_array($temp)) {
        $street = trim((string)($temp['street'] ?? ''));
        $suburb = trim((string)($temp['suburb'] ?? ''));
        $town = trim((string)($temp['town'] ?? ''));
        $province = trim((string)($temp['province'] ?? ''));
        $postalCode = trim((string)($temp['postal_code'] ?? ''));

        if ($street !== '' && $suburb !== '' && $town !== '' && $postalCode !== '') {
            return [
                'source' => 'temporary',
                'street' => $street,
                'suburb' => $suburb,
                'town' => $town,
                'province' => $province,
                'postal_code' => $postalCode,
            ];
        }
    }

    $profile = cart_find_client_profile($conn, $clientId);
    if (!$profile) {
        return null;
    }

    $street = trim((string)($profile['str_address'] ?? ''));
    $suburb = trim((string)($profile['phy_suburb'] ?? ''));
    $town = trim((string)($profile['phy_town'] ?? ''));
    $province = trim((string)($profile['phy_province'] ?? ''));
    $postalCode = trim((string)($profile['phy_postal_code'] ?? ''));

    if ($street === '' || $suburb === '' || $town === '' || $postalCode === '') {
        return null;
    }

    return [
        'source' => 'profile',
        'street' => $street,
        'suburb' => $suburb,
        'town' => $town,
        'province' => $province,
        'postal_code' => $postalCode,
    ];
}

function cart_format_address_lines(array $address): array
{
    $lines = [];
    $lineOne = trim((string)($address['street'] ?? ''));
    $lineTwoParts = array_filter([
        trim((string)($address['suburb'] ?? '')),
        trim((string)($address['town'] ?? '')),
    ], static fn(string $part): bool => $part !== '');
    $lineThreeParts = array_filter([
        trim((string)($address['province'] ?? '')),
        trim((string)($address['postal_code'] ?? '')),
    ], static fn(string $part): bool => $part !== '');

    if ($lineOne !== '') {
        $lines[] = $lineOne;
    }
    if ($lineTwoParts !== []) {
        $lines[] = implode(', ', $lineTwoParts);
    }
    if ($lineThreeParts !== []) {
        $lines[] = implode(' ', $lineThreeParts);
    }

    return $lines;
}

function cart_get_financial_account_balances(mysqli $conn, int $clientId): array
{
    $stmt = $conn->prepare(
        "SELECT
            fa.id,
            fa.account_name,
            fa.account_type,
            fa.opening_balance,
            COALESCE(SUM(
                CASE
                    WHEN ft.txn_type IN ('deposit', 'income') THEN ABS(ft.amount)
                    WHEN ft.txn_type IN ('withdrawal', 'expense') THEN -ABS(ft.amount)
                    ELSE ft.amount
                END
            ), 0) AS movement_total
         FROM financial_accounts fa
         LEFT JOIN financial_transactions ft ON ft.account_id = fa.id
         WHERE fa.client_id = ?
         GROUP BY fa.id, fa.account_name, fa.account_type, fa.opening_balance
         ORDER BY fa.id ASC"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $accounts = [];

    while ($row = $result->fetch_assoc()) {
        $openingBalance = (float)($row['opening_balance'] ?? 0);
        $movementTotal = (float)($row['movement_total'] ?? 0);
        $accounts[] = [
            'id' => (int)$row['id'],
            'account_name' => (string)($row['account_name'] ?? ''),
            'account_type' => (string)($row['account_type'] ?? ''),
            'balance' => $openingBalance + $movementTotal,
        ];
    }

    $stmt->close();

    return $accounts;
}

function cart_get_terminal_balance(mysqli $conn, int $clientId): float
{
    $accounts = cart_get_account_balances_by_types($conn, $clientId, ['credit'], true);
    return cart_get_positive_balance_total($accounts);
}

function cart_get_card_balance(mysqli $conn, int $clientId): float
{
    $accounts = cart_get_account_balances_by_types($conn, $clientId, ['credit']);
    return cart_get_positive_balance_total($accounts);
}

function cart_get_account_balances_by_types(mysqli $conn, int $clientId, array $accountTypes, bool $excludeMatches = false): array
{
    $accounts = cart_get_financial_account_balances($conn, $clientId);
    if ($accountTypes === []) {
        return $accounts;
    }

    return array_values(array_filter(
        $accounts,
        static function (array $account) use ($accountTypes, $excludeMatches): bool {
            $matches = in_array((string)($account['account_type'] ?? ''), $accountTypes, true);
            return $excludeMatches ? !$matches : $matches;
        }
    ));
}

function cart_get_positive_balance_total(array $accounts): float
{
    $total = 0.0;
    foreach ($accounts as $account) {
        $balance = (float)($account['balance'] ?? 0);
        if ($balance > 0) {
            $total += $balance;
        }
    }

    return $total;
}

function cart_debit_account_funds(
    mysqli $conn,
    int $clientId,
    float $amount,
    string $description,
    array $accountTypes,
    bool $excludeMatches = false
): bool {
    if ($amount <= 0) {
        return true;
    }

    $accounts = cart_get_account_balances_by_types($conn, $clientId, $accountTypes, $excludeMatches);
    $available = cart_get_positive_balance_total($accounts);

    if ($available + 0.00001 < $amount) {
        return false;
    }

    try {
        $insert = $conn->prepare(
            "INSERT INTO financial_transactions (account_id, txn_type, amount, description, created_at)
             VALUES (?, 'withdrawal', ?, ?, NOW())"
        );
        if (!$insert) {
            throw new RuntimeException('Unable to prepare payment transaction.');
        }

        $remaining = $amount;

        foreach ($accounts as $account) {
            $balance = max(0.0, (float)$account['balance']);
            if ($balance <= 0 || $remaining <= 0) {
                continue;
            }

            $debitAmount = min($balance, $remaining);
            $accountId = (int)$account['id'];
            $signedDebitAmount = -$debitAmount;
            $insert->bind_param('ids', $accountId, $signedDebitAmount, $description);
            if (!$insert->execute()) {
                throw new RuntimeException('Unable to record a payment deduction.');
            }
            $remaining -= $debitAmount;
        }

        $insert->close();

        if ($remaining > 0.00001) {
            throw new RuntimeException('Payment deduction was incomplete.');
        }

        return true;
    } catch (Throwable $throwable) {
        return false;
    }
}

function cart_debit_terminal_funds(mysqli $conn, int $clientId, float $amount, string $description): bool
{
    return cart_debit_account_funds($conn, $clientId, $amount, $description, ['credit'], true);
}

function cart_debit_card_funds(mysqli $conn, int $clientId, float $amount, string $description): bool
{
    return cart_debit_account_funds($conn, $clientId, $amount, $description, ['credit']);
}

function cart_get_checkout_context(mysqli $conn): array
{
    cart_bootstrap();

    $buyerId = (int)($_SESSION['user_info']['id'] ?? 0);
    $total = cart_get_total($conn);
    $address = $buyerId > 0 ? cart_get_confirmed_address($conn, $buyerId) : null;
    $terminalBalance = $buyerId > 0 ? cart_get_terminal_balance($conn, $buyerId) : 0.0;
    $cardBalance = $buyerId > 0 ? cart_get_card_balance($conn, $buyerId) : 0.0;
    $shortfall = max(0.0, $total - $terminalBalance);

    return [
        'buyer_id' => $buyerId,
        'total' => $total,
        'address' => $address,
        'terminal_balance' => $terminalBalance,
        'card_balance' => $cardBalance,
        'has_address' => $address !== null,
        'has_terminal_funds' => $terminalBalance + 0.00001 >= $total,
        'has_card_funds' => $cardBalance + 0.00001 >= $total,
        'shortfall' => $shortfall,
    ];
}

function cart_insert_message(mysqli $conn, int $senderId, int $receiverId, string $subject, string $message): void
{
    notifications_send($conn, $senderId, $receiverId, $subject, $message);
}

function cart_ensure_fulfillment_tables(mysqli $conn): void
{
    marketplace_orders_ensure_tables($conn);
}

function cart_create_order_reference(int $buyerId, int $vendorId): string
{
    try {
        $random = strtoupper(bin2hex(random_bytes(4)));
    } catch (Throwable $throwable) {
        $random = strtoupper(substr(md5((string)microtime(true)), 0, 8));
    }

    return 'ORD-' . $buyerId . '-' . $vendorId . '-' . $random;
}

function cart_insert_marketplace_order(mysqli $conn, array $group, array $buyer, array $address, string $paymentMethod): ?array
{
    $vendorUserId = (int)($group['vendor_user_id'] ?? 0);
    $vendorId = (int)($group['vendor_id'] ?? 0);
    if ($vendorUserId <= 0 || $vendorId <= 0) {
        return null;
    }

    $orderRef = cart_create_order_reference((int)$buyer['id'], $vendorId);
    $totalAmount = 0.0;
    foreach ($group['items'] as $item) {
        $totalAmount += (float)($item['line_total'] ?? 0);
    }

    $stmt = $conn->prepare(
        "INSERT INTO marketplace_orders
        (order_ref, buyer_id, vendor_id, vendor_user_id, buyer_username, vendor_name, total_amount, order_status, package_status, payment_method, delivery_street, delivery_suburb, delivery_town, delivery_province, delivery_postal_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', 'pending_packaging', ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return null;
    }

    $buyerId = (int)$buyer['id'];
    $buyerUsername = (string)($buyer['username'] ?? '');
    $vendorName = (string)($group['vendor_name'] ?? '');
    $deliveryStreet = (string)($address['street'] ?? '');
    $deliverySuburb = (string)($address['suburb'] ?? '');
    $deliveryTown = (string)($address['town'] ?? '');
    $deliveryProvince = (string)($address['province'] ?? '');
    $deliveryPostalCode = (string)($address['postal_code'] ?? '');
    $stmt->bind_param(
        'siiissdssssss',
        $orderRef,
        $buyerId,
        $vendorId,
        $vendorUserId,
        $buyerUsername,
        $vendorName,
        $totalAmount,
        $paymentMethod,
        $deliveryStreet,
        $deliverySuburb,
        $deliveryTown,
        $deliveryProvince,
        $deliveryPostalCode
    );
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to save the marketplace order.');
    }
    $orderId = (int)$stmt->insert_id;
    $stmt->close();
    if ($orderId <= 0) {
        throw new RuntimeException('Marketplace order did not return a valid ID.');
    }

    $itemStmt = $conn->prepare(
        "INSERT INTO marketplace_order_items
        (order_id, product_id, product_name, quantity, unit_price, line_total, vendor_id, buyer_id, unit_cost, cost_total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$itemStmt) {
        throw new RuntimeException('Unable to prepare marketplace order items.');
    }
    if ($itemStmt) {
        foreach ($group['items'] as $item) {
            $productId = (int)($item['id'] ?? 0);
            $productName = (string)($item['product_name'] ?? '');
            $quantity = (int)($item['quantity'] ?? 1);
            $unitPrice = (float)($item['sale_price'] ?? 0);
            $lineTotal = (float)($item['line_total'] ?? 0);
            $unitCost = (float)($item['cost_price'] ?? 0);
            $costTotal = round($unitCost * $quantity, 2);
            $itemStmt->bind_param('iisiddiidd', $orderId, $productId, $productName, $quantity, $unitPrice, $lineTotal, $vendorId, $buyerId, $unitCost, $costTotal);
            if (!$itemStmt->execute()) {
                $itemStmt->close();
                throw new RuntimeException('Unable to save a marketplace order item.');
            }
        }
        $itemStmt->close();
    }

    $costOfGoodsSold = 0.0;
    foreach ($group['items'] as $accountingItem) {
        $costOfGoodsSold += round((float)($accountingItem['cost_price'] ?? 0) * (int)($accountingItem['quantity'] ?? 1), 2);
    }
    $commission = 0.00; // No commission policy/configuration exists yet.
    $vendorPayable = round($totalAmount - $commission, 2);
    $accountingStmt = $conn->prepare(
        "INSERT INTO marketplace_accounting_entries
         (order_id, order_ref, buyer_id, vendor_id, vendor_user_id, gross_sales, cost_of_goods_sold, platform_commission, vendor_payable, entry_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_settlement')"
    );
    if (!$accountingStmt) {
        throw new RuntimeException('Unable to prepare marketplace accounting record.');
    }
    $accountingStmt->bind_param('isiiidddd', $orderId, $orderRef, $buyerId, $vendorId, $vendorUserId, $totalAmount, $costOfGoodsSold, $commission, $vendorPayable);
    if (!$accountingStmt->execute()) {
        $accountingStmt->close();
        throw new RuntimeException('Unable to save marketplace accounting record.');
    }
    $accountingStmt->close();

    return [
        'order_id' => $orderId,
        'order_ref' => $orderRef,
        'total_amount' => $totalAmount,
        'payment_method' => $paymentMethod,
    ];
}

function cart_add_item(mysqli $conn, int $productId, int $quantity = 1): bool
{
    cart_bootstrap();

    $product = cart_find_product($conn, $productId);
    if (!$product) {
        return false;
    }

    $quantity = cart_normalize_quantity($quantity);

    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = $quantity;
    }

    return true;
}

function cart_update_item(int $productId, int $quantity): void
{
    cart_bootstrap();

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$productId]);
        return;
    }

    $_SESSION['cart'][$productId] = $quantity;
}

function cart_remove_item(int $productId): void
{
    cart_bootstrap();
    unset($_SESSION['cart'][$productId]);
}

function cart_clear_items(): void
{
    $_SESSION['cart'] = [];
}

function cart_count_items(): int
{
    cart_bootstrap();
    return array_sum(array_map('intval', $_SESSION['cart']));
}

function cart_get_items(mysqli $conn): array
{
    cart_bootstrap();

    $items = [];

    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $product = cart_find_product($conn, (int)$productId);
        if (!$product) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        $qty = cart_normalize_quantity($quantity);
        $price = (float)($product['sale_price'] ?? 0);

        $items[] = [
            'id' => (int)$product['id'],
            'vendor_id' => (int)($product['vendor_id'] ?? 0),
            'vendor_name' => (string)($product['vendor_name'] ?? ''),
            'product_name' => (string)($product['product_name'] ?? ''),
            'description' => (string)($product['description'] ?? ''),
            'measure' => (string)($product['measure'] ?? ''),
            'product_img' => (string)($product['product_img'] ?? ''),
            'sale_price' => $price,
            'quantity' => $qty,
            'line_total' => $price * $qty,
        ];
    }

    return $items;
}

function cart_get_total(mysqli $conn): float
{
    $total = 0.0;
    foreach (cart_get_items($conn) as $item) {
        $total += (float)$item['line_total'];
    }

    return $total;
}

function cart_checkout(mysqli $conn, string $paymentMethod = 'terminal'): array
{
    cart_bootstrap();
    cart_ensure_fulfillment_tables($conn);

    $buyerId = (int)($_SESSION['user_info']['id'] ?? 0);
    if ($buyerId <= 0) {
        return ['success' => false, 'message' => 'Please log in before checking out.'];
    }

    $buyer = cart_find_client($conn, $buyerId);
    if (!$buyer) {
        return ['success' => false, 'message' => 'Buyer account could not be found.'];
    }

    $address = cart_get_confirmed_address($conn, $buyerId);
    if ($address === null) {
        return ['success' => false, 'message' => 'Please confirm a delivery address before paying.'];
    }

    $items = cart_get_items($conn);
    if ($items === []) {
        return ['success' => false, 'message' => 'Your cart is empty.'];
    }

    $paymentMethod = $paymentMethod === 'card' ? 'card' : 'terminal';
    $cartTotal = 0.0;
    $vendorGroups = [];

    // Rebuild order totals from current database product prices, not posted/cart-stored prices.
    foreach ($items as $item) {
        $productId = (int)$item['id'];
        $quantity = (int)$item['quantity'];
        $product = cart_find_product($conn, $productId);
        if (!$product || (int)($product['vendor_id'] ?? 0) <= 0) {
            return ['success' => false, 'message' => 'A product in your cart is no longer available. Please review your cart.'];
        }
        $availableUnits = (int)($product['units'] ?? 0);
        if ($availableUnits < $quantity) {
            return ['success' => false, 'message' => 'There is not enough stock for ' . (string)$product['product_name'] . '. Please update your cart.'];
        }
        $unitPrice = (float)($product['sale_price'] ?? 0);
        if (!is_finite($unitPrice) || $unitPrice < 0) {
            return ['success' => false, 'message' => 'A product has an invalid price. Please contact the vendor.'];
        }
        $vendorId = (int)$product['vendor_id'];
        if (!isset($vendorGroups[$vendorId])) {
            $vendorGroups[$vendorId] = [
                'vendor_id' => $vendorId,
                'vendor_name' => (string)($product['vendor_name'] ?? ''),
                'vendor_user_id' => 0,
                'items' => [],
            ];
        }
        $item['vendor_id'] = $vendorId;
        $item['vendor_name'] = (string)($product['vendor_name'] ?? '');
        $item['product_name'] = (string)($product['product_name'] ?? $item['product_name']);
        $item['sale_price'] = $unitPrice;
        $item['cost_price'] = max(0.0, (float)($product['cost_price'] ?? 0));
        $item['line_total'] = round($unitPrice * $quantity, 2);
        $vendorGroups[$vendorId]['items'][] = $item;
        $cartTotal += $item['line_total'];
    }

    foreach ($vendorGroups as $vendorId => $group) {
        $stmt = $conn->prepare('SELECT client_id, reg_name FROM vendors WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Unable to validate a vendor for this order.'];
        }
        $stmt->bind_param('i', $vendorId);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Unable to validate a vendor for this order.'];
        }
        $result = $stmt->get_result();
        $vendor = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$vendor || (int)($vendor['client_id'] ?? 0) <= 0) {
            return ['success' => false, 'message' => 'A vendor account is unavailable. Please review your cart.'];
        }
        $vendorGroups[$vendorId]['vendor_user_id'] = (int)$vendor['client_id'];
        $vendorGroups[$vendorId]['vendor_name'] = (string)($vendor['reg_name'] ?? $group['vendor_name']);
    }

    $conn->begin_transaction();
    try {
        // Lock product rows and recheck stock/prices inside the transaction to reduce overselling.
        foreach ($vendorGroups as $group) {
            foreach ($group['items'] as $item) {
                $productId = (int)$item['id'];
                $quantity = (int)$item['quantity'];
                $lock = $conn->prepare('SELECT id, units, sale_price, cost_price FROM products WHERE id = ? FOR UPDATE');
                if (!$lock) throw new RuntimeException('Unable to lock product inventory.');
                $lock->bind_param('i', $productId);
                if (!$lock->execute()) { $lock->close(); throw new RuntimeException('Unable to verify product inventory.'); }
                $result = $lock->get_result();
                $lockedProduct = $result ? $result->fetch_assoc() : null;
                $lock->close();
                if (!$lockedProduct || (int)$lockedProduct['units'] < $quantity || abs((float)$lockedProduct['sale_price'] - (float)$item['sale_price']) > 0.00001 || abs((float)($lockedProduct['cost_price'] ?? 0) - (float)($item['cost_price'] ?? 0)) > 0.00001) {
                    throw new RuntimeException('Stock or pricing changed during checkout. Please review your cart and try again.');
                }
            }
        }

        // Lock the buyer's account rows so concurrent checkouts cannot spend the same balance.
        $accountLock = $conn->prepare('SELECT id FROM financial_accounts WHERE client_id = ? FOR UPDATE');
        if (!$accountLock) throw new RuntimeException('Unable to lock buyer financial accounts.');
        $accountLock->bind_param('i', $buyerId);
        if (!$accountLock->execute()) { $accountLock->close(); throw new RuntimeException('Unable to lock buyer financial accounts.'); }
        $accountLock->close();

        $accountTypes = $paymentMethod === 'card' ? ['credit'] : ['credit'];
        $excludeMatches = $paymentMethod === 'terminal';
        $accounts = cart_get_account_balances_by_types($conn, $buyerId, $accountTypes, $excludeMatches);
        $available = cart_get_positive_balance_total($accounts);
        if ($available + 0.00001 < $cartTotal) {
            throw new RuntimeException($paymentMethod === 'terminal'
                ? 'Your terminal balance is not enough for this order. Make a deposit or pay by card.'
                : 'Your card account does not have enough available balance for this order.');
        }

        $description = $paymentMethod === 'card' ? 'Marketplace order card payment' : 'Marketplace order payment';
        if (!cart_debit_account_funds($conn, $buyerId, $cartTotal, $description, $accountTypes, $excludeMatches)) {
            throw new RuntimeException('We could not deduct the funds for this order. Please try again.');
        }

        foreach ($vendorGroups as $group) {
            $orderRecord = cart_insert_marketplace_order($conn, $group, $buyer, $address, $paymentMethod);
            if (!$orderRecord) {
                throw new RuntimeException('An order could not be recorded. No payment was finalized.');
            }

            foreach ($group['items'] as $item) {
                $productId = (int)$item['id'];
                $quantity = (int)$item['quantity'];
                $stock = $conn->prepare('UPDATE products SET units = units - ? WHERE id = ? AND units >= ?');
                if (!$stock) throw new RuntimeException('Unable to prepare stock update.');
                $stock->bind_param('iii', $quantity, $productId, $quantity);
                if (!$stock->execute() || $stock->affected_rows !== 1) {
                    $stock->close();
                    throw new RuntimeException('Stock changed while placing the order. Please try again.');
                }
                $stock->close();

                $movementQty = -1 * $quantity;
                $movementCost = max(0.0, (float)($item['cost_price'] ?? 0));
                $movementValue = round($movementQty * $movementCost, 2);
                $movementType = 'sale';
                $movementRef = (string)$orderRecord['order_ref'];
                $actorId = $buyerId;
                $movement = $conn->prepare("INSERT INTO inventory_movements (vendor_id, product_id, order_id, movement_type, quantity_delta, unit_cost, value_delta, reference, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$movement) throw new RuntimeException('Unable to prepare inventory movement record.');
                $orderIdForMovement = (int)$orderRecord['order_id'];
                $movement->bind_param('iiisdddsi', $vendorId, $productId, $orderIdForMovement, $movementType, $movementQty, $movementCost, $movementValue, $movementRef, $actorId);
                if (!$movement->execute()) { $movement->close(); throw new RuntimeException('Unable to record inventory movement.'); }
                $movement->close();
            }

            accounting_post_marketplace_order($conn, (int)$orderRecord['order_id'], (int)$group['vendor_id'], (string)$orderRecord['order_ref'], $buyerId);

            $productSummary = [];
            foreach ($group['items'] as $item) {
                $productSummary[] = (string)$item['product_name'] . ' x' . (int)$item['quantity'];
            }
            $summaryText = implode(', ', $productSummary);
            $paymentLabel = $paymentMethod === 'card' ? 'card account' : 'terminal balance';
            $addressText = implode(', ', cart_format_address_lines($address));
            $vendorMessage = "Order {$orderRecord['order_ref']} received from {$buyer['username']} (user_id {$buyerId}) for {$summaryText}. Payment method: {$paymentLabel}. Delivery address: {$addressText}. Package it in QR Shop to create tracking.";
            $buyerMessage = "Purchase confirmed for order {$orderRecord['order_ref']} with vendor {$group['vendor_name']} (vendor_id {$group['vendor_id']}) for {$summaryText}. Payment method: {$paymentLabel}. Delivery address: {$addressText}.";
            cart_insert_message($conn, $buyerId, (int)$group['vendor_user_id'], 'Marketplace Order Received', $vendorMessage);
            cart_insert_message($conn, (int)$group['vendor_user_id'], $buyerId, 'Marketplace Order Confirmed', $buyerMessage);
        }

        $conn->commit();
        cart_clear_items();
        cart_clear_temporary_address();
        unset($_SESSION['cart_payment_shortfall']);
        return ['success' => true, 'message' => 'Checkout completed successfully. Vendor and buyer messages were sent.'];
    } catch (Throwable $throwable) {
        $conn->rollback();
        if (str_contains(strtolower($throwable->getMessage()), 'balance is not enough') || str_contains(strtolower($throwable->getMessage()), 'does not have enough')) {
            if ($paymentMethod === 'terminal') {
                $_SESSION['cart_payment_shortfall'] = max(0.0, $cartTotal - cart_get_terminal_balance($conn, $buyerId));
            }
        }
        error_log('Marketplace checkout failed: ' . $throwable->getMessage());
        return ['success' => false, 'message' => $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Checkout failed. No payment was finalized.'];
    }
}

function cart_format_currency(float $amount): string
{
    return 'R' . number_format($amount, 2);
}

function get_client_products_cart(object $conn, string $user_id = ''): void
{
    foreach (cart_get_items($conn) as $item) {
        $img = $item['product_img'] !== ''
            ? '/cnrbyte/uploads/products/' . rawurlencode($item['product_img'])
            : '/cnrbyte/uploads/products/default_product.png';

        echo "<div class='cart-legacy-item'>";
        echo "<img src='" . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') . "' style='width:90px;height:90px;object-fit:cover;border-radius:8px;'>";
        echo "<p><strong>" . htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') . "</strong></p>";
        echo "<p>" . cart_format_currency((float)$item['sale_price']) . " x " . (int)$item['quantity'] . "</p>";
        echo "</div>";
    }
}

function get_cart_items_total(object $conn, string $user_id = ''): void
{
    echo cart_format_currency(cart_get_total($conn));
}

function insert_to_cart($conn, string $client_id, string $vendor_id, string $pro_id, string $product_img, string $brand, string $name, string $description, string $sale_price, string $cost_price, string $units): void
{
    cart_add_item($conn, (int)$pro_id, (int)$units);
}

function edit_cart($conn, string $remove_code, string $item_id): void
{
    cart_remove_item((int)$item_id);
}
