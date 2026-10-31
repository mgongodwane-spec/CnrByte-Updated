<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once __DIR__ . '/../includes/conn.dbh.php';

$errors = [];
$currentClientId = (int)($_SESSION['user_info']['id'] ?? 0);
$currentRank = strtolower((string)($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? 'guest'));
$isAdmin = in_array($currentRank, ['admin', 'administrator'], true);
if ($currentClientId < 1 || !in_array($currentRank, ['client', 'vendor', 'admin', 'administrator'], true)) { http_response_code(403); exit('Please sign in with an authorized account.'); }

$redirectSettings = static function (string $query = ''): never {
    header('Location: /cnrbyte/settings/settings.pge.php' . ($query !== '' ? '?' . $query : ''));
    exit;
};
$fail = static function (string $message) use ($redirectSettings): never {
    $_SESSION['errors_create'] = ['settings' => $message];
    $redirectSettings();
};

// 1. Lookup/type values are restricted to the categories used by the application.
if (isset($_POST['vendor_type'])) {
    $allowedTypes = ['business_type', 'bank_name', 'acc_type', 'contract_type', 'leave_type', 'earnings_type', 'deductions_type', 'document_type', 'qualification_type', 'share_type', 'occupation_type'];
    $type = trim((string)($_POST['type'] ?? ''));
    $typeName = trim((string)($_POST['type_name'] ?? ''));
    if (!in_array($type, $allowedTypes, true)) $fail('Select a valid type category.');
    if ($typeName === '' || mb_strlen($typeName) > 180) $fail('Type name is required and must be 180 characters or fewer.');

    $lookupOwnerId = $currentClientId;
    if (isset($_SESSION['vendor_info']['id'])) {
        $activeVendorId = (int)$_SESSION['vendor_info']['id'];
        $ownerStmt = $conn->prepare("SELECT COALESCE(client_id, user_id) AS owner_id FROM vendors WHERE id = ? AND status = 'approved' LIMIT 1");
        $ownerStmt->bind_param('i', $activeVendorId); $ownerStmt->execute(); $ownerRow = $ownerStmt->get_result()->fetch_assoc(); $ownerStmt->close();
        if (!$ownerRow || (!$isAdmin && (int)$ownerRow['owner_id'] !== $currentClientId)) { http_response_code(403); exit('You do not have access to this vendor workspace.'); }
        $lookupOwnerId = (int)$ownerRow['owner_id'];
    }
    $stmt = $conn->prepare('SELECT id FROM types WHERE client_id = ? AND type = ? AND LOWER(TRIM(type_name)) = LOWER(TRIM(?)) LIMIT 1');
    $stmt->bind_param('iss', $lookupOwnerId, $type, $typeName);
    $stmt->execute();
    $duplicate = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($duplicate) $fail('That type already exists in your settings.');

    $stmt = $conn->prepare('INSERT INTO types (type, type_name, client_id) VALUES (?, ?, ?)');
    $stmt->bind_param('ssi', $type, $typeName, $lookupOwnerId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_create'] = ['type' => 'Type created successfully.'];
    $redirectSettings('type=' . rawurlencode($type));
}

// 2. Admin-only dashboard configuration.
if (isset($_POST['admin_type'])) {
    if (!$isAdmin) { http_response_code(403); exit('Administrator access required.'); }
    $type = (string)($_POST['type'] ?? '');
    $typeName = trim((string)($_POST['type_name'] ?? ''));
    if (!in_array($type, ['form', 'table'], true)) $fail('Select a valid dashboard object type.');
    if ($typeName === '' || mb_strlen($typeName) > 100) $fail('Object name is required and must be 100 characters or fewer.');
    // Do not trust the dashboard context supplied by hidden inputs.
    $currentDashboard = 'settings_dashboard';
    $selectedPage = 'settings_pge';
    $stmt = $conn->prepare('INSERT INTO admin_objs (type, type_name, current_dashboard, selected_pge) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $type, $typeName, $currentDashboard, $selectedPage);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_create'] = ['admin_type' => 'Dashboard configuration item created.'];
    $redirectSettings();
}

// 3. Organisational ranks must belong to the active vendor workspace and its owner.
if (isset($_POST['insert_rank'])) {
    $vendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
    if ($vendorId < 1) $fail('Select a vendor workspace before creating organisational ranks.');
    $stmt = $conn->prepare("SELECT id, COALESCE(client_id, user_id) AS owner_id FROM vendors WHERE id = ? AND status = 'approved' LIMIT 1");
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$vendor || (!$isAdmin && (int)$vendor['owner_id'] !== $currentClientId)) { http_response_code(403); exit('You do not have access to this vendor workspace.'); }

    $rankLevel = filter_var($_POST['rank'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    $rankName = trim((string)($_POST['rank_name'] ?? ''));
    $occupation = trim((string)($_POST['occupation'] ?? ''));
    $rateRaw = trim((string)($_POST['rate'] ?? ''));
    $rate = $rateRaw === '' ? 0.0 : filter_var($rateRaw, FILTER_VALIDATE_FLOAT);
    if ($rankLevel === false || $rankLevel === null || $rankName === '' || mb_strlen($rankName) > 150 || $occupation === '' || mb_strlen($occupation) > 150 || $rate === false || !is_numeric($rate) || (float)$rate < 0) {
        $fail('Provide a valid hierarchy level, rank name, occupation, and non-negative default pay rate.');
    }
    $ownerId = (int)$vendor['owner_id'];
    $occupationStmt = $conn->prepare("SELECT id FROM types WHERE client_id = ? AND type = 'occupation_type' AND type_name = ? LIMIT 1");
    $occupationStmt->bind_param('is', $ownerId, $occupation); $occupationStmt->execute(); $occupationExists = (bool)$occupationStmt->get_result()->fetch_assoc(); $occupationStmt->close();
    if (!$occupationExists) $fail('Select an occupation configured in Settings.');
    $rankValue = (string)$rankLevel;
    $stmt = $conn->prepare('INSERT INTO ranks (client_id, vendor_id, rank, rank_name, qualification_rank, occupation, rate, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $qualificationRank = 'not_applicable';
    $rateValue = (float)$rate;
    $stmt->bind_param('iissssd', $ownerId, $vendorId, $rankValue, $rankName, $qualificationRank, $occupation, $rateValue);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_create'] = ['rank' => 'Organisational rank created successfully.'];
    $redirectSettings();
}

// 4. Admin product and QR creation. Verify vendor and validate all fields before writes.
if (isset($_POST['admin_create_full_product'])) {
    if (!$isAdmin) { http_response_code(403); exit('Administrator access required.'); }
    $vendorId = filter_var($_POST['vendor_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $productName = trim((string)($_POST['product_name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $measure = (string)($_POST['measure'] ?? '');
    $names = [];
    foreach (['great_name', 'grand_name', 'parent_name', 'child_name'] as $field) {
        $names[$field] = trim((string)($_POST[$field] ?? ''));
        if ($names[$field] === '' || mb_strlen($names[$field]) > 120) $fail('All four category names are required and must be 120 characters or fewer.');
    }
    if ($vendorId === false || $vendorId === null || $productName === '' || mb_strlen($productName) > 180 || mb_strlen($description) > 5000 || !in_array($measure, ['kilograms', 'pieces', 'litres'], true)) {
        $fail('Enter a valid vendor, product name, description, and unit of measure.');
    }
    $stmt = $conn->prepare("SELECT id, COALESCE(client_id, user_id) AS owner_id FROM vendors WHERE id = ? AND status = 'approved' LIMIT 1");
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$vendor || (int)$vendor['owner_id'] < 1) $fail('The selected vendor could not be found.');
    $ownerId = (int)$vendor['owner_id'];

    $filename = 'default_product.png';
    $uploadedPath = null;
    if (isset($_FILES['product_img']) && (int)($_FILES['product_img']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['product_img'];
        $ext = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
        $mimeMap = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']];
        if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($upload['tmp_name'] ?? '')) || (int)($upload['size'] ?? 0) > 5242880 || !isset($mimeMap[$ext])) $fail('The product image must be a valid JPG, PNG or WebP image under 5 MB.');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (!in_array((string)$finfo->file((string)$upload['tmp_name']), $mimeMap[$ext], true)) $fail('The uploaded image content does not match its extension.');
        $uploadDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/cnrbyte/uploads/products';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) $fail('Product image storage is unavailable.');
        $filename = 'adm_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadedPath = $uploadDir . '/' . $filename;
        if (!move_uploaded_file((string)$upload['tmp_name'], $uploadedPath)) $fail('The product image could not be saved.');
    }

    $qrPath = null;
    $conn->begin_transaction();
    try {
        require_once __DIR__ . '/../includes/product_hierarchy.php';
        product_hierarchy_ensure_tables($conn);
        $greatId = product_hierarchy_ensure_great_category($conn, $names['great_name']);
        $grandId = product_hierarchy_ensure_grand_category($conn, $greatId, $names['grand_name']);
        $parentCategoryId = product_hierarchy_ensure_parent_category($conn, $greatId, $grandId, $names['parent_name']);
        $childId = product_hierarchy_ensure_child_category($conn, $greatId, $grandId, $parentCategoryId, $names['child_name']);
        if (!product_hierarchy_is_valid_path($conn, $greatId, $grandId, $parentCategoryId, $childId)) throw new RuntimeException('Invalid product category path.');

        $stmt = $conn->prepare("INSERT INTO products (vendor_id, great, grand, parent, child, product_name, description, measure, product_img, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ready for Pickup')");
        $stmt->bind_param('iiiiissss', $vendorId, $greatId, $grandId, $parentCategoryId, $childId, $productName, $description, $measure, $filename);
        $stmt->execute(); $productId = (int)$conn->insert_id; $stmt->close();

        require_once __DIR__ . '/../marketplace/products/qr_code/generate_logic.php';
        generateProductQR($productId, $conn);
        $qrPath = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/cnrbyte/uploads/products/qr_codes/qr_' . $productId . '.png';
        $conn->commit();
        $_SESSION['success_create'] = ['full_product' => 'Product hierarchy and QR code created successfully.'];
        $redirectSettings('success=full');
    } catch (Throwable $e) {
        $conn->rollback();
        if ($uploadedPath !== null && is_file($uploadedPath)) @unlink($uploadedPath);
        if ($qrPath !== null && is_file($qrPath)) @unlink($qrPath);
        error_log('CnrByte admin product creation failed: ' . $e->getMessage());
        $fail('The product could not be created. Check the vendor, database schema, and QR generator, then try again.');
    }
}

http_response_code(400);
exit('No supported settings action was submitted.');
