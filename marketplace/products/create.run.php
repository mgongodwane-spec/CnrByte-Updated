<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.dbh.php'; // Added to ensure redirection_logic exists
require_once "../../includes/product_hierarchy.php";

$errors = [];
product_hierarchy_bootstrap($conn);

// =============================
// CATEGORY CREATION
// =============================
if (isset($_POST['new_category'])) {
    $inputs = ['category'];
    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }

    $vendor_id = $_SESSION["vendor_info"]["id"];
    $category  = product_hierarchy_normalize_name((string)($_POST['category'] ?? ''));
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    $rank      = (string)($_POST['rank'] ?? 'child');

    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['category_inputs'] = $_POST;
        redirection_logic();
    }

    $createdId = 0;
    if ($rank === 'great') {
        $existing = product_hierarchy_get_one(
            $conn,
            "SELECT id FROM product_great_categories WHERE category = ? LIMIT 1",
            's',
            [$category]
        );
        if ($existing) {
            $_SESSION['errors_create'] = ['duplicate' => 'This category already exists.'];
            redirection_logic();
        }
        $createdId = product_hierarchy_ensure_great_category($conn, $category);
    } elseif ($rank === 'grand') {
        $great = product_hierarchy_get_one(
            $conn,
            "SELECT id FROM product_great_categories WHERE id = ? LIMIT 1",
            'i',
            [$parent_id]
        );
        if (!$great) {
            $_SESSION['errors_create'] = ['parent' => 'Select a valid great category first.'];
            redirection_logic();
        }
        $existing = product_hierarchy_get_one(
            $conn,
            "SELECT id FROM product_grand_categories WHERE great_id = ? AND category = ? LIMIT 1",
            'is',
            [$parent_id, $category]
        );
        if ($existing) {
            $_SESSION['errors_create'] = ['duplicate' => 'This category already exists.'];
            redirection_logic();
        }
        $createdId = product_hierarchy_ensure_grand_category($conn, $parent_id, $category);
    } elseif ($rank === 'parent') {
        $grand = product_hierarchy_get_one(
            $conn,
            "SELECT id, great_id FROM product_grand_categories WHERE id = ? LIMIT 1",
            'i',
            [$parent_id]
        );
        if (!$grand) {
            $_SESSION['errors_create'] = ['parent' => 'Select a valid grand category first.'];
            redirection_logic();
        }
        $existing = product_hierarchy_get_one(
            $conn,
            "SELECT id FROM product_parent_categories WHERE great_id = ? AND grand_id = ? AND category = ? LIMIT 1",
            'iis',
            [(int)$grand['great_id'], $parent_id, $category]
        );
        if ($existing) {
            $_SESSION['errors_create'] = ['duplicate' => 'This category already exists.'];
            redirection_logic();
        }
        $createdId = product_hierarchy_ensure_parent_category($conn, (int)$grand['great_id'], $parent_id, $category);
    } else {
        $parent = product_hierarchy_get_one(
            $conn,
            "SELECT id, great_id, grand_id FROM product_parent_categories WHERE id = ? LIMIT 1",
            'i',
            [$parent_id]
        );
        if (!$parent) {
            $_SESSION['errors_create'] = ['parent' => 'Select a valid parent category first.'];
            redirection_logic();
        }
        $existing = product_hierarchy_get_one(
            $conn,
            "SELECT id FROM product_child_categories WHERE great_id = ? AND grand_id = ? AND parent_id = ? AND category = ? LIMIT 1",
            'iiis',
            [(int)$parent['great_id'], (int)$parent['grand_id'], $parent_id, $category]
        );
        if ($existing) {
            $_SESSION['errors_create'] = ['duplicate' => 'This category already exists.'];
            redirection_logic();
        }
        $createdId = product_hierarchy_ensure_child_category($conn, (int)$parent['great_id'], (int)$parent['grand_id'], $parent_id, $category);
    }

    if ($createdId <= 0) {
        $_SESSION['errors_create'] = ['category' => 'Unable to create category.'];
        redirection_logic();
    }

    $_SESSION['success_create']['category'] = "New category created successfully.";
    redirection_logic();
}

// =============================
// COST ITEM CREATION
// =============================
if (isset($_POST["create_cost_item"])) {
    if (!isset($_SESSION["vendor_info"]["id"])) {
        $_SESSION["errors_create"]["vendor"] = "Select a vendor first.";
        redirection_logic();
    }

    $vendor_id = (int)$_SESSION["vendor_info"]["id"];
    $type = trim((string)($_POST["type"] ?? ""));
    $cost_item_name = trim((string)($_POST["cost_item_name"] ?? ""));
    $description = trim((string)($_POST["description"] ?? ""));
    $measure = trim((string)($_POST["measure"] ?? ""));
    $source_product_id = (int)($_POST["source_product_id"] ?? 0);

    if ($source_product_id > 0) {
        $source_product_info = get_product_info_by_product_id($conn, $source_product_id);
        if ($source_product_info && (int)$source_product_info["vendor_id"] === $vendor_id) {
            if ($type === '') {
                $type = 'ingredients';
            }
            if ($cost_item_name === '') {
                $cost_item_name = (string)$source_product_info["product_name"];
            }
            if ($description === '') {
                $description = "Ingredient from product: " . (string)$source_product_info["product_name"];
            }
            if ($measure === '') {
                $measure = (string)$source_product_info["measure"];
            }

            $source_sale_price = (float)($source_product_info["sale_price"] ?? 0);
            $source_cost_price = (float)($source_product_info["cost_price"] ?? 0);
        }
    }

    $required = ["type" => $type, "cost_item_name" => $cost_item_name, "description" => $description, "measure" => $measure];
    foreach ($required as $key => $value) {
        if ($value === '') {
            $errors["empty_{$key}"] = ucfirst(str_replace('_', ' ', $key)) . " required.";
        }
    }

    if ($errors) {
        $_SESSION["errors_create"] = $errors;
        $_SESSION["create_cost_item_inputs"] = $_POST;
        redirection_logic();
    }

    $dup = $conn->prepare("SELECT id FROM cost_items WHERE vendor_id = ? AND type = ? AND cost_item_name = ?");
    $dup->bind_param("iss", $vendor_id, $type, $cost_item_name);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $_SESSION["errors_create"]["duplicate_cost_item"] = "This cost item already exists for this vendor.";
        $dup->close();
        redirection_logic();
    }
    $dup->close();

    $sale_price = isset($source_sale_price) ? $source_sale_price : 0;
    $cost_price = isset($source_cost_price) ? $source_cost_price : 0;

    $sql = "INSERT INTO cost_items (vendor_id, type, cost_item_name, description, measure, sale_price, cost_price) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssdd", $vendor_id, $type, $cost_item_name, $description, $measure, $sale_price, $cost_price);
    $stmt->execute();
    $stmt->close();

    unset($_SESSION["create_cost_item_inputs"]);
    $_SESSION["success_create"]["cost_item_created"] = "Cost item created successfully.";
    redirection_logic();
}

// =============================
// PRODUCT CREATION
// =============================
if (isset($_POST["create_product"])) {
    $vendor_id = $_SESSION["vendor_info"]["id"];
    $inputs = ['product_name', 'description', 'measure'];

    // Validate required product fields
    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }

    // Validate that category fields are present (either from hierarchy or manual selection)
    $great = (int)($_POST['great'] ?? 0);
    $grand = (int)($_POST['grand'] ?? 0);
    $parent = (int)($_POST['parent'] ?? 0);
    $child = (int)($_POST['child'] ?? 0);

    if ($great <= 0 || $grand <= 0 || $parent <= 0 || $child <= 0) {
        $errors["categories"] = "Please select a complete category path (Great > Grand > Parent > Child).";
    }
    if (!product_hierarchy_is_valid_path($conn, $great, $grand, $parent, $child)) {
        $errors["categories"] = "The selected category path is not valid.";
    }

    $rawSalePrice = $_POST['sale_price'] ?? null;
    $rawCostPrice = $_POST['cost_price'] ?? null;
    $validSalePrice = is_scalar($rawSalePrice) && is_numeric((string)$rawSalePrice) && is_finite((float)$rawSalePrice) && (float)$rawSalePrice >= 0 && (float)$rawSalePrice <= 9999999999.99;
    $validCostPrice = is_scalar($rawCostPrice) && is_numeric((string)$rawCostPrice) && is_finite((float)$rawCostPrice) && (float)$rawCostPrice >= 0 && (float)$rawCostPrice <= 9999999999.99;
    if (!$validSalePrice || !$validCostPrice) {
        $errors["pricing"] = "Enter valid non-negative sale and cost prices (maximum R9,999,999,999.99).";
    }
    $salePrice = $validSalePrice ? round((float)$rawSalePrice, 2) : 0.0;
    $costPrice = $validCostPrice ? round((float)$rawCostPrice, 2) : 0.0;

    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['create_products_inputs'] = $_POST;
        redirection_logic();
    }

    // Handle File Upload
    $filename = "";
    $max_file_size = 5 * 1024 * 1024; // 5MB

    if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['product_img'];

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
            $errors['upload_error'] = $upload_errors[$file['error']] ?? 'Unknown upload error.';
        } elseif ($file['size'] > $max_file_size) {
            $errors['file_size'] = 'File exceeds 5MB limit.';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $errors['invalid_file'] = 'Invalid uploaded file.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp'];
            $allowed_mime = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
                $errors['file_type'] = 'Only JPG, JPEG, JFIF, PNG, GIF, and WebP images allowed.';
            } else {
                $upload_path = $_SERVER['DOCUMENT_ROOT'] . "/cnrbyte/uploads/products/";
                if (!is_dir($upload_path) || !is_writable($upload_path)) {
                    $errors['upload_dir'] = 'Upload directory not accessible.';
                } else {
                    // Use a generated filename; never trust client-provided filenames.
                    $safe_name = 'product_' . bin2hex(random_bytes(12)) . '.' . $ext;
                    $target = $upload_path . $safe_name;
                    if (!move_uploaded_file($file['tmp_name'], $target)) {
                        $errors['move_file'] = 'Failed to save uploaded file.';
                    } else {
                        $filename = $safe_name;
                    }
                }
            }
        }

        if (!empty($errors)) {
            $_SESSION['errors_create'] = array_merge($errors, $_SESSION['errors_create'] ?? []);
            $_SESSION['create_products_inputs'] = $_POST;
            redirection_logic();
            exit;
        }
    }

    $sql = "INSERT INTO products (vendor_id, great, grand, parent, child, product_name, description, measure, product_img, sale_price, cost_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiiissssdd', $vendor_id, $great, $grand, $parent, $child, $_POST['product_name'], $_POST['description'], $_POST['measure'], $filename, $salePrice, $costPrice);
    $stmt->execute();
    $stmt->close();

    unset($_SESSION['create_products_inputs']);
    $_SESSION['success_create']['product_created'] = "Product created successfully.";
    redirection_logic();
}

// =============================
// COST OF SALE LINKING
// =============================
if (isset($_POST["create_cost_of_sale"])) {
    $vendor_id = $_SESSION["vendor_info"]["id"];
    $inputs = ['product_id', 'type_id']; // Required minimums

    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = "Missing required link data.";
        }
    }

    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        redirection_logic();
    }

    $product_id     = (int)$_POST["product_id"];
    $cost_item_id   = (int)$_POST['type_id'];

    // Fetch fresh info from helper functions
    $cost_item_info = get_cost_item_info_by_cost_item_id($conn, $cost_item_id);
    $product_info   = get_product_info_by_product_id($conn, $product_id);
    if (!$cost_item_info || (int)($cost_item_info['vendor_id'] ?? 0) !== (int)$vendor_id) {
        $_SESSION['errors_create'] = ['cost_item' => 'Select a cost item belonging to the active vendor workspace.'];
        redirection_logic();
    }
    if (!$product_info || (int)($product_info['vendor_id'] ?? 0) !== (int)$vendor_id) {
        $_SESSION['errors_create'] = ['product' => 'Select a product belonging to the active vendor workspace.'];
        redirection_logic();
    }

    $sql = "INSERT INTO cost_of_sales (vendor_id, product_id, cost_item_id, product_name, type, cost_item_name, description, measure, sale_price, cost_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // Bind Params: i=int, s=string, d=double/price
    $stmt->bind_param('iiisssssdd',
        $vendor_id,
        $product_id,
        $cost_item_id,
        $product_info['product_name'],
        $cost_item_info['type'],
        $cost_item_info['cost_item_name'],
        $cost_item_info['description'],
        $cost_item_info['measure'],
        $cost_item_info['sale_price'],
        $cost_item_info['cost_price']
    );

    if ($stmt->execute()) {
        $_SESSION['success_create']['cos'] = "Cost item linked to product successfully.";
    } else {
        $_SESSION['errors_create']['db'] = "Execution failed.";
    }
    $stmt->close();
    redirection_logic();
}
