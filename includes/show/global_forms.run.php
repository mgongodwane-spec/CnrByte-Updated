<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
if (empty($_SESSION['user_info']['id'])) { http_response_code(403); exit('Please sign in first.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid or expired form token.'); }
$currentDashboard = (string)($_SESSION['current_dashboard'] ?? '');
$clientId = (int)$_SESSION['user_info']['id'];
$ownerMap = [
    'vendors_dashboard' => ['vendor_id','vendor_info','id'],
    'branches_dashboard' => ['branch_id','branch_info','id'],
    'shifts_dashboard' => ['shift_id','shift_info','id'],
    'staff_dashboard' => ['staff_id','staff_info','id'],
    'clients_dashboard' => ['client_id','user_info','id'],
];
if (!isset($ownerMap[$currentDashboard])) { http_response_code(403); exit('Select a valid workspace before editing records.'); }
[$column,$sessionKey,$idKey] = $ownerMap[$currentDashboard];
$owners_id = (int)($_SESSION[$sessionKey][$idKey] ?? 0);
$reg_name = (string)($_SESSION['user_info']['username'] ?? 'client');
if ($owners_id < 1) { http_response_code(403); exit('No valid profile was selected.'); }
if (in_array($currentDashboard, ['vendors_dashboard','branches_dashboard','shifts_dashboard','staff_dashboard'], true)) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
    $vendorId = (int)$_SESSION['vendor_info']['id'];
    if ($currentDashboard === 'branches_dashboard') { $q=$conn->prepare('SELECT id FROM branches WHERE id=? AND vendor_id=? LIMIT 1'); $q->bind_param('ii',$owners_id,$vendorId); }
    elseif ($currentDashboard === 'shifts_dashboard') { $q=$conn->prepare('SELECT id FROM shifts WHERE id=? AND vendor_id=? LIMIT 1'); $q->bind_param('ii',$owners_id,$vendorId); }
    elseif ($currentDashboard === 'staff_dashboard') { $q=$conn->prepare('SELECT id FROM staff WHERE id=? AND vendor_id=? LIMIT 1'); $q->bind_param('ii',$owners_id,$vendorId); }
    else { $q=$conn->prepare('SELECT id FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1'); $q->bind_param('iii',$owners_id,$clientId,$clientId); }
    $q->execute(); $ownerValid=(bool)$q->get_result()->fetch_assoc(); $q->close();
    if (!$ownerValid) { http_response_code(403); exit('The selected record does not belong to this vendor workspace.'); }
} elseif ($owners_id !== $clientId) { http_response_code(403); exit('Client profile access denied.'); }

// Initialize error tracking
$errors = [];
$success = [];

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

function cnrbyte_upload_official_document($file, string $prefix, string $folder, array $allowed = ['jpg','jpeg','png','pdf'], int $max_size = 50000000): array {
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'File upload error'];
    if (empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) return [false, 'Invalid uploaded file'];
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $max_size) return [false, 'File too large or empty'];
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedMimeByExt = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'pdf' => ['application/pdf'],
    ];
    if (!in_array($ext, $allowed, true) || !isset($allowedMimeByExt[$ext])) return [false, 'Invalid file type'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$file['tmp_name']);
    if (!in_array($mime, $allowedMimeByExt[$ext], true)) return [false, 'File content does not match its extension'];
    $base = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/cnrbyte/uploads/official_docs/' . trim($folder, '/');
    if (!is_dir($base) && !mkdir($base, 0750, true) && !is_dir($base)) return [false, 'Upload directory unavailable'];
    try { $random = bin2hex(random_bytes(16)); } catch (Throwable $e) { return [false, 'Secure filename generation failed']; }
    $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix);
    $newName = substr($safePrefix ?: 'document', 0, 40) . '_' . $random . '.' . $ext;
    if (!move_uploaded_file((string)$file['tmp_name'], $base . '/' . $newName)) return [false, 'Failed to save uploaded file'];
    return [true, $newName];
}
function upload_file_banking($file, $prefix, $allowed = ['jpg','jpeg','png','pdf'], $max_size = 50000000) {
    return cnrbyte_upload_official_document($file, (string)$prefix, 'banking', $allowed, (int)$max_size);
}
function upload_file_profile($file, $prefix, $allowed = ['jpg','jpeg','png','pdf'], $max_size = 50000000) {
    return cnrbyte_upload_official_document($file, (string)$prefix, 'address', $allowed, (int)$max_size);
}
function upload_file_official_doc($file, $prefix, $allowed = ['jpg','jpeg','png','pdf'], $max_size = 50000000) {
    return cnrbyte_upload_official_document($file, (string)$prefix, 'documents', $allowed, (int)$max_size);
}

if($_SESSION["current_dashboard"] == 'vendors_dashboard'){
    $owners_id = $_SESSION["vendor_info"]["id"];
    $column = 'vendor_id';
    $reg_name = $_SESSION["vendor_info"]["reg_name"];
}
if($_SESSION["current_dashboard"] == 'branches_dashboard'){
    $owners_id = $_SESSION["branch_info"]["id"];
    $column = 'branch_id';
    $reg_name = $_SESSION["branch_info"]["branch_name"];
}
if($_SESSION["current_dashboard"] == 'shifts_dashboard'){
    $owners_id = $_SESSION["shift_info"]["id"];
    $column = 'shift_id';
    $reg_name = $_SESSION["shift_info"]["shift_name"];
}
if($_SESSION["current_dashboard"] == 'staff_dashboard'){
    $owners_id = $_SESSION["staff_info"]["id"];
    $column = 'staff_id';
    $reg_name = $_SESSION["staff_info"]["first_names"];
}

if (isset($_POST['edit_banking_details'])) {

    // expected fields
    $inputs = [
        'bank_name','branch_code', 'acc_type',
        'acc_number'
    ];

    // validate required
    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }

    if(empty($_FILES['statement_img']['name'])){
        $errors['statement_img'] = "Insert Bank Statement Image!";
    }

    if($errors){
        $_SESSION["errors_create"] = $errors;
        redirection_logic();
    }

    list($success, $filename_or_error) = upload_file_banking($_FILES['statement_img'], $reg_name);
    if(!$success){
        echo $filename_or_error;
        exit;
    }

    // handle validation errors
    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['settings_inputs'] = $_POST;
        redirection_logic();
    }

    // check duplicate type_name in table
    $stmt = $conn->prepare("SELECT id FROM banking WHERE $column = ?");
    $stmt->bind_param("i", $owners_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        $stmt->close();
        $stmt = $conn->prepare(
            "UPDATE banking SET
            bank_name = ?,
            branch_code = ?,
            acc_type = ?,
            acc_number = ?,
            statement_img = ?
            WHERE $column = ?"
        );
        $stmt->bind_param("sssssi", $_POST['bank_name'], $_POST['branch_code'], $_POST['acc_type'], $_POST['acc_number'], $filename_or_error, $owners_id);
        $stmt->execute();

        $_SESSION['success_create']['duplicate'] = 'Banking Details Updated.';
        redirection_logic();
    }else{

        // insert new type
        $stmt->close();
        $stmt = $conn->prepare("
            INSERT INTO banking (
                $column, bank_name, branch_code,
                acc_type, acc_number, statement_img
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?
            )
        ");
        $stmt->bind_param("isssss", $owners_id, $_POST['bank_name'], $_POST['branch_code'], $_POST['acc_type'], $_POST['acc_number'], $filename_or_error);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_create']['banking'] = "Banking created successfully.";
        redirection_logic();
    }
    $_SESSION['errors_create']['un_handled_case'] = "Un-handled Case!";
    redirection_logic();
}
if (isset($_POST['edit_phy_address'])) {

    // expected fields
    $inputs = [
        'str_address','phy_suburb', 'phy_town',
        'phy_province', 'phy_postal_code'
    ];

    // validate required
    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }

    if(empty($_FILES['phy_address_img']['name'])){
        $errors['phy_address_img'] = "Insert Proof of address Image!";
    }

    if($errors){
        $_SESSION["errors_create"] = $errors;
        redirection_logic();
    }

    list($success, $filename_or_error) = upload_file_profile($_FILES['phy_address_img'], $reg_name);
    if(!$success){
        echo $filename_or_error;
        exit;
    }

    // handle validation errors
    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['settings_inputs'] = $_POST;
        redirection_logic();
    }

    // check duplicate type_name in table
    $stmt = $conn->prepare("SELECT id FROM profiles WHERE $column = ?");
    $stmt->bind_param("i", $owners_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        $stmt->close();
        // insert new type
        $stmt = $conn->prepare(
            "UPDATE profiles SET
                str_address = ?,
                phy_suburb = ?,
                phy_town = ?,
                phy_province = ?,
                phy_postal_code = ?,
                phy_address_img = ?
            WHERE $column = ?
        ");
        $stmt->bind_param("ssssssi", $_POST['str_address'], $_POST['phy_suburb'], $_POST['phy_town'], $_POST['phy_province'], $_POST['phy_postal_code'], $filename_or_error, $owners_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_create']['address_update'] = "Physycal Address Updated successfully.";
        redirection_logic();
    }else{
        // insert new type
        $stmt->close();
        $stmt = $conn->prepare("
            INSERT INTO profiles (
                $column, str_address, phy_suburb,
                phy_town, phy_postal_code, phy_address_img
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?
            )
        ");
        $stmt->bind_param("isssss", $owners_id, $_POST['str_address'], $_POST['phy_suburb'], $_POST['phy_town'], $_POST['phy_postal_code'], $filename_or_error);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_create']['address_creation'] = "Physycal Address created successfully.";
        redirection_logic();
    }
    $_SESSION['errors_create']['un_handled_case'] = "Un-handled Case!";
    redirection_logic();
}
if (isset($_POST['edit_pos_address'])) {

    // expected fields
    $inputs = [
        'po_box','pos_suburb', 'pos_town',
        'pos_province', 'pos_postal_code'
    ];

    // validate required
    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }


    if($errors){
        $_SESSION["errors_create"] = $errors;
        redirection_logic();
    }

    // handle validation errors
    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['settings_inputs'] = $_POST;
        redirection_logic();
    }

    // check duplicate type_name in table
    $stmt = $conn->prepare("SELECT id FROM profiles WHERE $column = ?");
    $stmt->bind_param("i", $owners_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        $stmt->close();
        // insert new type
        $stmt = $conn->prepare(
            "UPDATE profiles SET
                po_box = ?,
                pos_suburb = ?,
                pos_town = ?,
                pos_province = ?,
                pos_postal_code = ?
            WHERE $column = ?
        ");
        $stmt->bind_param("sssssi", $_POST['po_box'], $_POST['pos_suburb'], $_POST['pos_town'], $_POST['pos_province'], $_POST['pos_postal_code'], $owners_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_create']['address_update'] = "Postal Address Updated successfully.";
        redirection_logic();
    }else{
        // insert new type
        $stmt->close();
        $stmt = $conn->prepare("
            INSERT INTO profiles (
                $column, po_box, pos_suburb,
                pos_town, pos_province, pos_postal_code
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?
            )
        ");
        $stmt->bind_param("isssss", $owners_id, $_POST['po_box'], $_POST['pos_suburb'], $_POST['pos_town'], $_POST['pos_province'], $_POST['pos_postal_code']);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_create']['address_creation'] = "Postal Address created successfully.";
        redirection_logic();
    }
    $_SESSION['errors_create']['un_handled_case'] = "Un-handled Case!";
    redirection_logic();
}
if(isset($_POST["edit_contacts"])) {
    $contact_name = $_POST['contact_name'] ?? '';
    $contact_address = $_POST['contact_address'] ?? '';
    $errors = check_empty(['contact_name' => $contact_name, 'contact_address' => $contact_address]);

    if($errors){
        $_SESSION["errors_create"] = $errors;
        redirection_logic();
    }

    $stmt = $conn->prepare("SELECT contact_name FROM contacts WHERE $column = ? AND contact_name = ?");
    $stmt->bind_param("is", $owners_id, $contact_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if($existing){
        $stmt = $conn->prepare("UPDATE contacts SET contact_address = ? WHERE $column = ? AND contact_name = ?");
        $stmt->bind_param("sis", $contact_address, $owners_id, $contact_name);
        $stmt->execute();
        $stmt->close();
        $_SESSION["success_create"]["contacts"] = "Contact Updated Successfully.";
        redirection_logic();
    } else {
        $stmt = $conn->prepare("INSERT INTO contacts ($column, contact_name, contact_address) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $owners_id, $contact_name, $contact_address);
        $stmt->execute();
        $stmt->close();
        $_SESSION["success_create"]["contacts"] = "Contact Created Successfully.";
        redirection_logic();
    }
    $_SESSION['errors_create']['un_handled_case'] = "Un-handled Case!";
    redirection_logic();
}
if(isset($_POST["insert_official_doc"])) {
    $document_name = $_POST['document_name'] ?? '';
    $serial_no = $_POST['serial_no'] ?? '';
    $errors = check_empty(['document_name' => $document_name, 'serial_no' => $serial_no]);

    if($errors || empty($_FILES['document_img']['name'])){
        if(empty($_FILES['document_img']['name'])) $errors['document_img'] = "Upload Document!";
        $_SESSION["errors_create"] = $errors;
        redirection_logic();
    }

    list($success, $document_img) = upload_file_official_doc($_FILES['document_img'], $reg_name, ['jpg','jpeg','png','pdf']);
    if(!$success){
        echo $document_img;
        $_SESSION['errors_create']['document'] = "Document Error!";
        redirection_logic();
    }

    $stmt = $conn->prepare("SELECT id FROM documents WHERE $column = ? AND (document_name = ? AND serial_number = ?)");
    $stmt->bind_param("iss", $owners_id, $document_name, $serial_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if($existing){
        $_SESSION['errors_create']['document'] = "That document aleady exists!";
        redirection_logic();
    }
    $stmt = $conn->prepare("SELECT id FROM documents WHERE serial_number = ?");
    $stmt->bind_param("s", $serial_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if($existing){
        $_SESSION['errors_create']['document'] = "That serial no exists for another document!";
        redirection_logic();
    }else{
        $stmt = $conn->prepare(
            "INSERT INTO documents ($column, document_name, serial_number, document_img)
            VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $owners_id, $document_name, $serial_no, $document_img);
        $stmt->execute();
        $stmt->close();
        $_SESSION["success_create"]["document"] = "Document Uploaded Successfully.";
        redirection_logic();
    }
    $_SESSION['errors_create']['un_handled_case'] = "Un-handled Case!";
    redirection_logic();
}
if(isset($_POST['create_org_ranks'])){
    $inputs = [
        'rank', 'rank_name', 'occupation',
        'rate'
    ];

    // validate required
    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }

    // if validation failed
    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['create_org_ranks_inputs'] = $_POST;
        redirection_logic();
    }

    // check duplicates
    $stmt = $conn->prepare("SELECT id FROM ranks WHERE $column = ? AND (rank_name = ? AND occupation = ?)");
    $stmt->bind_param("iss", $owners_id, $_POST["rank_name"], $_POST["occupation"]);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        $stmt->close();
        $errors["taken"] = "Already Exists.";
    } else {
        $stmt->close();
    }

    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        redirection_logic();
    }

    $occupation = trim((string)($_POST['occupation'] ?? ''));
    $lookupOwner = (int)($_SESSION['user_info']['id'] ?? 0);
    $lookup = $conn->prepare("SELECT id FROM types WHERE client_id = ? AND type = 'occupation_type' AND type_name = ? LIMIT 1");
    $lookup->bind_param('is', $lookupOwner, $occupation); $lookup->execute(); $occupationExists = (bool)$lookup->get_result()->fetch_assoc(); $lookup->close();
    if (!$occupationExists) { $_SESSION['errors_create'] = ['occupation' => 'Select an occupation configured in Settings.']; redirection_logic(); }
    // Keep the required qualification_rank field populated for compatibility.
    $stmt = $conn->prepare("INSERT INTO ranks (client_id, $column, rank, rank_name, qualification_rank, occupation, rate) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $client_id = $_SESSION["user_info"]["id"];
    $qualificationRank = 'not_applicable';
    $stmt->bind_param("iissssd", $client_id, $owners_id, $_POST['rank'], $_POST['rank_name'], $qualificationRank, $occupation, $_POST['rate']);
    $stmt->execute();
    $stmt->close();
    $_SESSION["success_create"]["org_creation"] = 'Org Rank Created';
    redirection_logic();
}
