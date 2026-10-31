<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid or expired form token.'); }



require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';

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
    $destination = "../uploads/staff/profile/" . $new_name;
    if (!move_uploaded_file($file['tmp_name'], $destination)) return [false, "Failed to move uploaded file"];
    return [true, $new_name];
}

if(isset($_POST["edit_staff_qualification"])) {
    $errors = [];
    $qualification = $_POST['qualification'] ?? '';
    $qualification_field = $_POST['qualification_field'] ?? '';

    $errors = check_empty([
        'qualification' => $qualification,
        'qualification_field' => $qualification_field
    ]);

    if(empty($_FILES['qualification_img']['name'])){
        $errors['qualification_img'] = "Insert Qualification Image!";
    }

    if($errors){
        $_SESSION["errors_create"] = $errors;
        redirection_logic();
    }

    $staff_id = $_SESSION["staff_info"]["id"];
    $identity_no = $_SESSION["staff_info"]["identity_no"];
    $staff_name = $_SESSION["staff_info"]["first_names"];

    list($success, $filename_or_error) = upload_file($_FILES['qualification_img'], $staff_name);
    if(!$success){
        echo $filename_or_error;
        $_SESSION['errors_create']['staff_edit'] = 'File Error!';
        redirection_logic();
    }

    $sql = "SELECT id FROM qualifications WHERE staff_id = ? AND qualification = ? AND qualification_field = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $staff_id, $qualification, $qualification_field);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result ? $result->fetch_assoc() : null;

    if($existing){
        $_SESSION["errors_create"]["staff_edit"] = 'Qualification Already Exists';
        redirection_logic();
    }elseif (!$existing) {
        // insert row
        $sql = "INSERT INTO qualifications (staff_id, qualification, qualification_field, qualification_img) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $staff_id, $_POST['qualification'], $_POST['qualification_field'], $filename_or_error);
        $stmt->execute();

        $_SESSION['success_create']['staff_created'] = "Staff Qualification created successfully.";
        redirection_logic();
    }{

    }
    $_SESSION["errors_create"]["un_handled_case"] = 'Un-handled Error';
    redirection_logic();
}
if(isset($_POST["edit_staff_contract"])) {
    $errors = [];
    $vendor_id = $_POST['vendor_id'] ?? '';
    $branch_id = $_POST['branch_id'] ?? '';
    $shift_id = $_POST['shift_id'] ?? '';
    $emp_code = $_POST['emp_code'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $rank_id = $_POST['rank_id'] ?? '';
    $contract = $_POST['contract'] ?? '';

    $errors = check_empty([
        'vendor_id' => $vendor_id,
        'branch_id' => $branch_id,
        'shift_id' => $shift_id,
        'emp_code' => $emp_code,
        'start_date' => $start_date,
        'rank_id' => $rank_id,
        'contract' => $contract
    ]);

    if($errors){
        $_SESSION["errors_create"] = $errors;
        redirection_logic();
    }
    $staff_id = $_SESSION["staff_info"]["id"];
    $identity_no = $_SESSION["staff_info"]["identity_no"];

    $rank_info = get_rank_info($conn, $_POST['rank_id']);

    $sql = "SELECT * FROM staff WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result ? $result->fetch_assoc() : null;

    if($existing){
        $sql = "UPDATE staff SET vendor_id = ?, branch_id = ?, shift_id = ?, emp_code = ?, start_date = ?, rank = ?, occupation = ?, rate = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssssssss', $_POST["vendor_id"], $_POST["branch_id"], $_POST["shift_id"], $_POST["emp_code"], $_POST["start_date"], $rank_info["rank_name"], $rank_info["occupation"], $rank_info["rate"], $staff_id);
        $stmt->execute();
        $_SESSION["success"]["staff_edit"] = 'Contract Update Successful';
        redirection_logic();
    }
    exit('Unhandled case');
}
if(isset($_POST["edit_profile_img"])) {
    $errors = [];
    if(empty($_FILES['profile_img']['name'])){
        $errors['profile_img'] = "Insert Profile Image!";
    }

    if($errors){
        $_SESSION["errors_creare"] = $errors;
        redirection_logic();
    }
    $staff_id = $_SESSION["staff_info"]["id"];
    $staff_name = $_SESSION["staff_info"]["first_names"];

    list($success, $filename_or_error) = upload_file($_FILES['profile_img'], $staff_name);
    if(!$success){
        echo $filename_or_error;
        $_SESSION['errors_create']['staff_edit'] = 'File Error!';
        redirection_logic();
    }

    $sql = "SELECT profile_img FROM profiles WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result ? $result->fetch_assoc() : null;

    if($existing){
        $sql = "UPDATE profiles SET profile_img = ? WHERE staff_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $filename_or_error, $staff_id);
        $stmt->execute();

        $_SESSION['success_create']['staff_edit'] = "Profile Image Update successfully.";
        redirection_logic();
    } else {
        $sql = "INSERT INTO profiles (staff_id, profile_img) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $staff_id, $filename_or_error);
        $stmt->execute();

        $_SESSION['success_create']['staff_edit'] = "Profile Image Created successfully.";
        redirection_logic();
    }
    $_SESSION['errors_create']['staff_edit'] = 'un-handled case!';
    redirection_logic();
}

?>
