<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';
require_once "../../includes/product_hierarchy.php";

$errors = [];
$vendor_id = $_SESSION["vendor_info"]["id"] ?? null;
$branch_id = $_SESSION["branch_info"]["id"] ?? 0; // Ensure branch_id is available
product_hierarchy_bootstrap($conn);

function product_price_is_valid($value): bool {
    if (!is_numeric($value)) return false;
    $amount = (float)$value;
    return is_finite($amount) && $amount >= 0 && $amount <= 9999999999.99;
}

function product_belongs_to_vendor(mysqli $conn, int $productId, int $vendorId): bool {
    if ($productId <= 0 || $vendorId <= 0) return false;
    $stmt = $conn->prepare('SELECT id FROM products WHERE id = ? AND vendor_id = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('ii', $productId, $vendorId);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found;
}

/**
 * Securely upload an image file
 * @return array [success, filename, error_message]
 */
function upload_product_image($file, $prefix = "product_", $max_size = 5242880): array {
    // Validate upload occurred
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, '', ''];
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds php.ini upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
        ];
        return [false, '', $upload_errors[$file['error']] ?? 'Unknown upload error.'];
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        return [false, '', 'File exceeds ' . ($max_size / 1024 / 1024) . 'MB limit.'];
    }

    // Validate it's an uploaded file
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, '', 'Invalid uploaded file.'];
    }

    // Validate file extension and MIME type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp'];
    $allowed_mime = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($ext, $allowed_ext)) {
        return [false, '', 'File extension not allowed. Only JPG, JPEG, JFIF, PNG, GIF, and WebP allowed.'];
    }

    // Check actual MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mime)) {
        return [false, '', 'File type not allowed. Only image files allowed.'];
    }

    // Ensure upload directory exists and is writable
    $upload_path = $_SERVER['DOCUMENT_ROOT'] . "/cnrbyte/uploads/products/";
    if (!is_dir($upload_path)) {
        @mkdir($upload_path, 0755, true);
    }

    if (!is_writable($upload_path)) {
        return [false, '', 'Upload directory not writable.'];
    }

    // Generate unique filename
    $filename = $prefix . uniqid('', true) . "." . $ext;
    $full_path = $upload_path . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $full_path)) {
        return [false, '', 'Failed to save uploaded file. Check server permissions.'];
    }

    return [true, $filename, ''];
}


// =============================
// PRODUCT SALES LOGIC
// =============================
if(isset($_POST["sell_product"])){
    $inputs = ['product_id', 'select_journal', 'sales', 'cost_of_sales', 'units_sold'];

    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }

    if($errors){
        $_SESSION["errors_create"] = $errors;
        redirection_logic();
    }

    $product_id     = (int)$_POST["product_id"];
    $select_journal = (string)$_POST["select_journal"];
    $raw_sales = $_POST["sales"];
    $raw_cost = $_POST["cost_of_sales"];
    $raw_units = $_POST["units_sold"];
    $sales          = (float)$raw_sales;
    $cost_of_sales  = (float)$raw_cost;
    $units          = (float)$raw_units;

    if (!product_price_is_valid($raw_sales) || !product_price_is_valid($raw_cost) || !is_numeric($raw_units) || !is_finite($units) || $units <= 0) {
        $_SESSION['errors_create']['sale_values'] = 'Enter valid non-negative sales and cost amounts, and units sold greater than zero.';
        redirection_logic();
        exit;
    }
    if (!in_array($select_journal, ['cpj', 'crj', 'dj'], true) || !product_belongs_to_vendor($conn, $product_id, (int)$vendor_id)) {
        $_SESSION['errors_create']['sale_product'] = 'The selected product or journal is invalid for this vendor.';
        redirection_logic();
        exit;
    }

    // Get product info only after confirming vendor ownership.
    $product_info = get_product_info_by_product_id($conn, $product_id);
    if (!$product_info) {
        $_SESSION['errors_create']['sale_product'] = 'Product could not be found.';
        redirection_logic();
        exit;
    }
    $item_name    = $product_info["product_name"];
    $details      = "Sold: " . $item_name;

    $sale_price   = $sales / $units;
    $cost_price   = $cost_of_sales / $units;

    // Determine Journal Table
    $table = ($select_journal === 'cpj' || $select_journal === 'crj') ? 'crj' : 'dj';
    $account_col = ($table === 'crj') ? 'bank' : 'debtors';

    // 1. Insert Transaction
    $sql = "INSERT INTO $table (vendor_id, branch_id, product_id, details, $account_col, sales, cost_of_sales, units, sale_price, cost_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiisdddddd', $vendor_id, $branch_id, $product_id, $details, $sales, $sales, $cost_of_sales, $units, $sale_price, $cost_price);
    $stmt->execute();

    // 2. Update Histogram (Sales)
    $avg_sale_hist = get_ave_from_relevant_tabe_by_product_id($conn, $product_id, 'sale_price', $table);
    $avg_sale_hist = $avg_sale_hist > 0 ? $avg_sale_hist : $sale_price; // Fallback to current if no history
    $change_rate_sale = $avg_sale_hist > 0 ? (($sale_price - $avg_sale_hist) / $avg_sale_hist) * 100 : 0;

    $sql_hist = "INSERT INTO histograms (vendor_id, branch_id, product_id, item_name, average_sale, actual_sale, change_rate) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_h = $conn->prepare($sql_hist);
    $stmt_h->bind_param('iiisddd', $vendor_id, $branch_id, $product_id, $item_name, $avg_sale_hist, $sale_price, $change_rate_sale);
    $stmt_h->execute();

    // 3. Update Product Margins
    $avg_sales_val = (float)$product_info["avg_sales"] ?: $sale_price;
    $avg_cost_val  = (float)$product_info["avg_cost_of_sales"] ?: $cost_price;

    $sale_margin_buffer = $avg_sales_val > 0 ? (($sale_price - $avg_sales_val) / $avg_sales_val) * 100 : 0;
    $avg_profit_margin  = $avg_sales_val - $avg_cost_val;
    $avg_profit_rate    = $avg_cost_val > 0 ? ($avg_profit_margin / $avg_cost_val) * 100 : 0;

    $sql_upd = "UPDATE products SET sale_margin_buffer_rate = ?, avg_profit_margin = ?, avg_profit_margin_rate = ? WHERE id = ? AND vendor_id = ?";
    $stmt_u = $conn->prepare($sql_upd);
    $stmt_u->bind_param('dddii', $sale_margin_buffer, $avg_profit_margin, $avg_profit_rate, $product_id, $vendor_id);
    $stmt_u->execute();

    $_SESSION["success_create"]["sale"] = 'Sale Successful.';
    redirection_logic();
}

// =============================
// EDIT PRODUCT DETAILS
// =============================
if(isset($_POST["edit_product_sale_price"])){
    $product_id = (int)($_POST['product_id'] ?? 0);
    $raw_price = $_POST['sale_price'] ?? null;
    if (!product_price_is_valid($raw_price) || !product_belongs_to_vendor($conn, $product_id, (int)$vendor_id)) {
        $_SESSION['errors_create']["edit_product_sale_price"] = "Enter a valid sale price for a product belonging to your vendor account.";
        redirection_logic();
        exit;
    }
    $sale_price = round((float)$raw_price, 2);
    $stmt = $conn->prepare("UPDATE products SET sale_price = ? WHERE id = ? AND vendor_id = ?");
    $stmt->bind_param('dii', $sale_price, $product_id, $vendor_id);
    $updated = $stmt->execute();
    $stmt->close();
    if (!$updated) {
        $_SESSION['errors_create']["edit_product_sale_price"] = "Sale price could not be updated.";
        redirection_logic();
        exit;
    }
    $_SESSION["product_info"] = get_product_info_by_product_id($conn, $product_id);
    $_SESSION['success_create']["edit_product_sale_price"] = "Product sale price updated successfully.";
    redirection_logic();
}
if(isset($_POST["edit_product_cost_price"])){
    $product_id = (int)($_POST['product_id'] ?? 0);
    $raw_price = $_POST['cost_price'] ?? null;
    if (!product_price_is_valid($raw_price) || !product_belongs_to_vendor($conn, $product_id, (int)$vendor_id)) {
        $_SESSION['errors_create']["edit_product_cost_price"] = "Enter a valid cost price for a product belonging to your vendor account.";
        redirection_logic();
        exit;
    }
    $cost_price = round((float)$raw_price, 2);
    $stmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ? AND vendor_id = ?");
    $stmt->bind_param('dii', $cost_price, $product_id, $vendor_id);
    $updated = $stmt->execute();
    $stmt->close();
    if (!$updated) {
        $_SESSION['errors_create']["edit_product_cost_price"] = "Cost price could not be updated.";
        redirection_logic();
        exit;
    }
    $_SESSION["product_info"] = get_product_info_by_product_id($conn, $product_id);
    $_SESSION['success_create']["edit_product_cost_price"] = "Product cost price updated successfully.";
    redirection_logic();
}
if(isset($_POST["edit_product"])){
    $product_id = (int)($_POST['product_id'] ?? 0);
    if (!product_belongs_to_vendor($conn, $product_id, (int)$vendor_id)) {
        $_SESSION['errors_create']['edit_product'] = 'You cannot edit a product that does not belong to your vendor account.';
        redirection_logic();
        exit;
    }
    $product_info = get_product_info_by_product_id($conn, $product_id);
    $filename = (string)($_POST['existing_img'] ?? ($product_info['product_img'] ?? '')); // Fallback to old image if no new one

    // Handle image upload if provided; preserve the old image only when no file was selected.
    if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] !== UPLOAD_ERR_NO_FILE) {
        [$upload_ok, $uploaded_filename, $upload_error] = upload_product_image(
            $_FILES['product_img'], 'product_' . $product_id . '_'
        );
        if (!$upload_ok) {
            $_SESSION['errors_create']['product_image'] = $upload_error ?: 'The product image could not be uploaded.';
            redirection_logic();
            exit;
        }
        $filename = $uploaded_filename;
    }

    $great = (int)($_POST['great'] ?? ($product_info['great'] ?? 0));
    $grand = (int)($_POST['grand'] ?? ($product_info['grand'] ?? 0));
    $parent = (int)($_POST['parent'] ?? ($product_info['parent'] ?? 0));
    $child = (int)($_POST['child'] ?? ($product_info['child'] ?? 0));

    if (!product_hierarchy_is_valid_path($conn, $great, $grand, $parent, $child)) {
        $_SESSION['errors_create']["edit_product_categories"] = "The selected product category path is not valid.";
        redirection_logic();
    }

    $sql = "UPDATE products SET great = ?, grand = ?, parent = ?, child = ?, product_name = ?, description = ?, measure = ?, product_img = ? WHERE id = ? AND vendor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiissssii', $great, $grand, $parent, $child, $_POST['product_name'], $_POST['description'], $_POST['measure'], $filename, $product_id, $vendor_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION["product_info"] = get_product_info_by_product_id($conn, $product_id);
    $_SESSION['success_create']["edit_product"] = "Product updated successfully.";
    redirection_logic();
}

// =============================
// STOCKING (PURCHASING) COST ITEMS
// =============================
if(isset($_POST["stock_cost_item"])){
    $cost_item_id = (int)($_POST["cost_item_id"] ?? 0);
    $raw_total_cost = $_POST["cost_price"] ?? null;
    $raw_units_bought = $_POST["units_bought"] ?? null;
    $total_cost   = (float)$raw_total_cost;
    $units_bought = (float)$raw_units_bought;
    if (!product_price_is_valid($raw_total_cost) || !is_numeric($raw_units_bought) || !is_finite($units_bought) || $units_bought <= 0) {
        $_SESSION['errors_create']['purchase_values'] = 'Enter a valid non-negative purchase cost and units bought greater than zero.';
        redirection_logic();
        exit;
    }
    $cost_item_info = get_cost_item_info_by_cost_item_id($conn, $cost_item_id);
    if (!$cost_item_info || (int)($cost_item_info['vendor_id'] ?? 0) !== (int)$vendor_id) {
        $_SESSION['errors_create']['purchase_item'] = 'The selected cost item does not belong to your vendor account.';
        redirection_logic();
        exit;
    }
    $actual_cost_per_unit = $total_cost / $units_bought;

    $details = "Purchased: " . $cost_item_info["description"];

    $table = ($_POST['select_journal'] === 'cj') ? 'cj' : 'cpj';
    $account_col = ($table === 'cj') ? 'creditors' : 'bank';

    // Insert into Journal
    $sql = "INSERT INTO $table (vendor_id, branch_id, cost_item_id, details, $account_col, units, cost_price, inventory) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiisdddd', $vendor_id, $branch_id, $cost_item_id, $details, $total_cost, $units_bought, $actual_cost_per_unit, $total_cost);
    $stmt->execute();

    // Update cost_items table with new Average Cost
    $new_avg_cost = get_ave_from_relevant_tabe_by_product_id($conn, $cost_item_id, 'cost_price', $table);
    $new_units = $cost_item_info['units'] + $units_bought;
    $new_inventory = $new_units * $new_avg_cost;

    $sql_upd = "UPDATE cost_items SET cost_price = ?, units = ?, inventory = ? WHERE id = ?";
    $stmt_u = $conn->prepare($sql_upd);
    $stmt_u->bind_param('dddi', $new_avg_cost, $new_units, $new_inventory, $cost_item_id);
    $stmt_u->execute();

    $_SESSION["success_create"]["purchase"] = 'Stock Updated Successfully.';
    redirection_logic();
}
