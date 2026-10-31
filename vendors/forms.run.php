<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
if (empty($_SESSION['user_info']['id'])) { http_response_code(403); exit('Please sign in first.'); }
if (!cnrbyte_csrf_validate()) { http_response_code(403); exit('Invalid or expired form token. Refresh the page and try again.'); }
$errors = [];

if(isset($_POST['create_new_vendor'])){
    $inputs = [
        'reg_no', 'reg_name', 'reg_date', 'starting_date',
        'business_type', 'business_form', 'operation_status', 'fin_year_end',
        'tax_no', 'phone', 'email'
    ];

    // Validate required values and server-side length limits.
    $limits = ['reg_no'=>120,'reg_name'=>180,'reg_date'=>10,'starting_date'=>10,'business_type'=>120,'business_form'=>120,'operation_status'=>120,'fin_year_end'=>10,'tax_no'=>120,'phone'=>50,'email'=>150];
    foreach ($inputs as $field) {
        $value = trim((string)($_POST[$field] ?? ''));
        if ($value === '') $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        if (mb_strlen($value) > $limits[$field]) $errors["length_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " is too long.";
        $_POST[$field] = $value;
    }
    foreach (['reg_date','starting_date','fin_year_end'] as $dateField) {
        $dateValue = (string)($_POST[$dateField] ?? '');
        $parsed = DateTime::createFromFormat('!Y-m-d', $dateValue);
        if (!$parsed || $parsed->format('Y-m-d') !== $dateValue) $errors["invalid_{$dateField}"] = 'Enter a valid ' . str_replace('_', ' ', $dateField) . '.';
    }

    if (!filter_var((string)($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $errors['invalid_email'] = "Invalid email format.";
    }

    if (!isset($_FILES['reg_cert']) || $_FILES['reg_cert']['error'] !== 0) {
        $errors['file'] = "Registration certificate required.";
    }

    // if validation failed
    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['create_vendor_inputs'] = $_POST;
        redirection_logic('/cnrbyte/vendors/create.pge.php');
    }

    // check duplicates
    foreach ([['reg_no','reg_no'], ['reg_name','reg_name'], ['tax_no','tax_no'], ['email','email']] as [$input,$column]) {
        $value = trim((string)($_POST[$input] ?? ''));
        if ($value === '') continue;
        $stmt = $conn->prepare("SELECT id FROM vendors WHERE LOWER(TRIM($column)) = LOWER(TRIM(?)) LIMIT 1");
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->fetch_assoc()) {
            $errors["{$column}_taken"] = "$column already registered.";
        }
        $stmt->close();
    }

    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        redirection_logic('/cnrbyte/vendors/create.pge.php');
    }

    // validate and upload file
    $file = $_FILES['reg_cert'];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'jfif', 'png', 'pdf'];
    $mime = ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
    $allowedMime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','jfif'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf'];
    if (!in_array($ext, $allowed, true) || !isset($allowedMime[$ext]) || $mime !== $allowedMime[$ext] || (int)$file['size'] > 5000000) {
        $_SESSION['errors_create'] = ['file_type' => "Invalid file type. Allowed: JPG, JPEG, JFIF, PNG, PDF."];
        redirection_logic('/cnrbyte/vendors/create.pge.php');
    }

    $filename = 'reg_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/cnrbyte/uploads/vendors/profile";
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0750, true) && !is_dir($upload_dir)) {
        $_SESSION['errors_create'] = ['upload_dir' => 'Upload folder could not be created.'];
        redirection_logic('/cnrbyte/vendors/create.pge.php');
    }

    $upload_path = $upload_dir . "/{$filename}";
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        $_SESSION['errors_create'] = ['upload_move' => 'Failed to upload registration certificate.'];
        redirection_logic('/cnrbyte/vendors/create.pge.php');
    }

    // insert row
    $client_id = (int) $_SESSION["user_info"]["id"];
    $market = 'local';
    $reg_cert = $filename;
    $reg_no = $_POST['reg_no'];
    $reg_name = $_POST['reg_name'];
    $reg_date = $_POST['reg_date'];
    $starting_date = $_POST['starting_date'];
    $business_type = $_POST['business_type'];
    $business_form = $_POST['business_form'];
    $operation_status = $_POST['operation_status'];
    $fin_year_end = $_POST['fin_year_end'];
    $tax_no = $_POST['tax_no'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    $stmt = $conn->prepare(
        "INSERT INTO vendors
        (
            client_id, user_id, market, reg_cert, reg_no, reg_name, reg_date, starting_date,
            business_type, business_form, operation_status, fin_year_end, tax_no, phone, email
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $user_id = $client_id;
    $types = 'iisssssssssssss'; // client_id + user_id + 13 text/date values
    $stmt->bind_param($types,
        $client_id, $user_id, $market, $reg_cert, $reg_no, $reg_name, $reg_date, $starting_date,
        $business_type, $business_form, $operation_status, $fin_year_end, $tax_no, $phone, $email
    );

    $stmt->execute();
    $newVendorId = (int)$stmt->insert_id;
    $stmt->close();
    $_SESSION['vendor_info'] = ['id'=>$newVendorId, 'client_id'=>$client_id, 'reg_name'=>$reg_name, 'reg_no'=>$reg_no, 'tax_no'=>$tax_no, 'status'=>'pending'];
    $_SESSION['current_dashboard'] = 'vendors_dashboard';

    $_SESSION["success_create"]["vendor_creation"] = 'Vendor Created';
    header('Location: /cnrbyte/vendors/dashboard.pge.php');
    exit;
}

// Helpers
function check_empty($fields) {
    $errors = [];
    foreach ($fields as $key => $val) {
        if (empty($val)) {
            $errors[$key] = ucfirst(str_replace('_', ' ', $key)) . " cannot be empty";
        }
    }
    return $errors;
}

function upload_file($file, $prefix, $allowed = ['jpg','jpeg','jfif','png','pdf'], $max_size = 5000000) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'File upload error'];
    if ((int)($file['size'] ?? 0) > $max_size || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) return [false, 'File too large or invalid upload'];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return [false, 'Invalid file type'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $allowedMime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','jfif'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf'];
    if (!isset($allowedMime[$ext]) || $mime !== $allowedMime[$ext]) return [false, 'File content does not match its extension'];
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/uploads/vendors/profile';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return [false, 'Upload directory unavailable'];
    $new_name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $new_name)) return [false, 'Failed to move uploaded file'];
    return [true, $new_name];
}

$vendor_id = (int)($_SESSION['vendor_info']['id'] ?? 0);
$reg_name = (string)($_SESSION['vendor_info']['reg_name'] ?? 'vendor');
if (isset($_POST['insert_edit_profile_logo']) || isset($_POST['insert_edit_profile_body'])) {
    $clientId = (int)$_SESSION['user_info']['id'];
    if ($vendor_id < 1) { http_response_code(403); exit('Select a vendor workspace first.'); }
    $ownerCheck = $conn->prepare('SELECT id, reg_name FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1');
    $ownerCheck->bind_param('iii', $vendor_id, $clientId, $clientId); $ownerCheck->execute(); $ownedVendor = $ownerCheck->get_result()->fetch_assoc(); $ownerCheck->close();
    if (!$ownedVendor) { unset($_SESSION['vendor_info']); http_response_code(403); exit('You do not have access to this vendor workspace.'); }
    $reg_name = (string)$ownedVendor['reg_name'];
}

if(isset($_POST["insert_edit_profile_logo"])) {
    $errors = [];
    if(empty($_FILES['logo_img']['name'])){
        $errors['logo_img'] = "Insert Logo Image!";
    }

    if($errors){
        $_SESSION["edit_profile_errors"] = $errors;
        redirection_logic();
    }

    list($success, $filename_or_error) = upload_file($_FILES['logo_img'], $reg_name);
    if(!$success){
        $_SESSION["errors_create"]["profile_update"] = 'File Error: ' . $filename_or_error;
        redirection_logic();
    }
    // check existing
    $stmt = $conn->prepare("SELECT logo_img FROM profiles WHERE vendor_id = ?");
    $stmt->bind_param('i', $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if($existing){
        $stmt = $conn->prepare("UPDATE profiles SET logo_img = ? WHERE vendor_id = ?");
        $stmt->bind_param('si', $filename_or_error, $vendor_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION["success_create"]["profile_update"] = 'Logo Update Successful.';
        redirection_logic();
    } else {
        $stmt = $conn->prepare("INSERT INTO profiles (vendor_id, logo_img) VALUES (?, ?)");
        $stmt->bind_param('is', $vendor_id, $filename_or_error);
        $stmt->execute();
        $stmt->close();
        $_SESSION["success_create"]["profile_update"] = 'Logo Insert Successful.';
        redirection_logic();
    }
    exit('Unhandled case');
}
if(isset($_POST["insert_edit_profile_body"])) {
    $heading = strtolower(trim((string)($_POST['heading'] ?? '')));
    $para_text = trim((string)($_POST['para_text'] ?? ''));
    $errors = check_empty(['heading' => $heading, 'para_text' => $para_text]);
    if (!in_array($heading, ['about','vision','mission','values'], true)) $errors['heading'] = 'Select a valid profile section.';

    if($errors || empty($_FILES['para_img']['name'])){
        if(empty($_FILES['para_img']['name'])) $errors['para_img'] = "Insert Paragraph Image!";
        $_SESSION["edit_profile_errors"] = $errors;
        redirection_logic();
    }

    list($success, $para_img) = upload_file($_FILES['para_img'], $reg_name);
    if(!$success){
        $_SESSION["errors_create"]["profile_update"] = 'File Error: ' . $para_img;
        redirection_logic();
    }

    $stmt = $conn->prepare("SELECT id FROM profiles WHERE vendor_id = ?");
    $stmt->bind_param('i', $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if($existing){
        // heading is a column prefix; ensure it's alphanumeric to reduce injection risk
        $safe_heading = preg_replace('/[^a-zA-Z0-9_]/', '', $heading);
        $col_text = $safe_heading . "_para_text";
        $col_img = $safe_heading . "_img";
        $sql = "UPDATE profiles SET {$col_text} = ?, {$col_img} = ? WHERE vendor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $para_text, $para_img, $vendor_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION["success_create"]["profile_update"] = 'Profile Updated Successful.';
        redirection_logic();
    }
}