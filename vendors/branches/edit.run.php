<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid or expired form token.'); }

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

$vendor_id = $_SESSION['vendor_info']['id'] ?? null;
$reg_name = $_SESSION['vendor_info']['reg_name'] ?? '';

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

function upload_file($file, $prefix, $allowed = ['jpg','jpeg','jfif','png','pdf'], $max_size = 50000000) {
    if ($file['error'] !== 0) return [false, "File upload error"];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return [false, "Invalid file type"];
    if ($file['size'] > $max_size) return [false, "File too large"];

    $new_name = $prefix . "." . uniqid('', true) . "." . $ext;
    $destination = "../uploads/vendors/profile/" . $new_name;
    if (!move_uploaded_file($file['tmp_name'], $destination)) return [false, "Failed to move uploaded file"];
    return [true, $new_name];
}
if(isset($_POST["edit_branch"])) {
    $branch_id = $_POST['branch_id'] ?? '';
    $branch_name = $_POST['branch_name'] ?? '';
    $branch_code = $_POST['branch_code'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';

    $errors = check_empty([
        'branch_id' => $branch_id,
        'branch_name' => $branch_name,
        'branch_code' => $branch_code,
        'phone' => $phone,
        'email' => $email
    ]);


    $stmt = $conn->prepare("SELECT * FROM branches WHERE id = ?");
    if (!$stmt) exit('DB error');
    $stmt->bind_param('i', $branch_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;

    if($existing){
        $sql = "UPDATE branches SET branch_name = ?, branch_code = ?, phone = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) exit('DB error');
        $stmt->bind_param('ssssi', $branch_name, $branch_code, $phone, $email, $branch_id);
        $stmt->execute();
        $stmt->close();

        header("Location: edit.pge.php?branch_update_successful");
        exit;
    }
    exit('Unhandled case');
}
if(isset($_POST["insert_update_profile_contacts"])) {
    $contact_name = $_POST['contact_name'] ?? '';
    $contact_address = $_POST['contact_address'] ?? '';
    $errors = check_empty(['contact_name' => $contact_name, 'contact_address' => $contact_address]);

    if($errors){
        $_SESSION["edit_profile_errors"] = $errors;
        header("Location: edit.pge.php?profile_contact_update_failed");
        exit;
    }

    $stmt = $conn->prepare("SELECT contact_name FROM contacts WHERE owners_id = ? AND contact_name = ?");
    if (!$stmt) exit('DB error');
    $stmt->bind_param('is', $vendor_id, $contact_name);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;

    if($existing){
        $stmt = $conn->prepare("UPDATE contacts SET contact_address = ? WHERE owners_id = ? AND contact_name = ?");
        if (!$stmt) exit('DB error');
        $stmt->bind_param('sis', $contact_address, $vendor_id, $contact_name);
        $stmt->execute();
        $stmt->close();
        header("Location: edit.pge.php?profile_contact_update_success");
    } else {
        $stmt = $conn->prepare("INSERT INTO contacts (owners_id, contact_name, contact_address) VALUES (?, ?, ?)");
        if (!$stmt) exit('DB error');
        $stmt->bind_param('iss', $vendor_id, $contact_name, $contact_address);
        $stmt->execute();
        $stmt->close();
        header("Location: edit.pge.php?profile_contact_insert_success");
    }
    exit;
}
if(isset($_POST["insert_update_profile_official_doc"])) {
    $document_name = $_POST['document_name'] ?? '';
    $serial_no = $_POST['serial_no'] ?? '';
    $errors = check_empty(['document_name' => $document_name, 'serial_no' => $serial_no]);

    if($errors || empty($_FILES['document_img']['name'])){
        if(empty($_FILES['document_img']['name'])) $errors['document_img'] = "Upload Document!";
        $_SESSION["edit_profile_errors"] = $errors;
        header("Location: edit.pge.php?document_update_failed_empty");
        exit;
    }

    list($success, $document_img) = upload_file($_FILES['document_img'], $reg_name, ['jpg','jpeg','jfif','png','pdf']);
    if(!$success){
        echo $document_img;
        exit;
    }

    $stmt = $conn->prepare("SELECT document_name FROM documents WHERE owners_id = ? AND document_name = ?");
    if (!$stmt) exit('DB error');
    $stmt->bind_param('is', $vendor_id, $document_name);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;

    if($existing){
        $_SESSION['document_error'] = "That document aleady exists!";
        header("Location: edit.pge.php?document_update_failed_name_exists");
        exit;
    }
    $stmt = $conn->prepare("SELECT serial_number FROM documents WHERE owners_id = ? AND serial_number = ?");
    if (!$stmt) exit('DB error');
    $stmt->bind_param('is', $vendor_id, $serial_no);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;

    if($existing){
        $_SESSION['serial_no_error'] = "That serial no exists for another document!";
        header("Location: edit.pge.php?document_update_failed_serial_exists");
        exit;
    }else{
        $stmt = $conn->prepare("INSERT INTO documents (owners_id, document_name, serial_number, document_img) VALUES (?, ?, ?, ?)");
        if (!$stmt) exit('DB error');
        $stmt->bind_param('isss', $vendor_id, $document_name, $serial_no, $document_img);
        $stmt->execute();
        $stmt->close();
        header("Location: edit.pge.php?document_insert_success");
        exit;
    }
    exit('Unhandled case');
}
?>
