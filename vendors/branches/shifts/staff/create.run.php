<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid or expired form token.'); }



require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';

if(isset($_POST["create_staff"])){
    $inputs = [
        'vendor_id', 'branch_id', 'shift_id',
        'identity_no', 'first_names','last_name',
        'emp_code', 'start_date', 'rank_id', 'contract'
    ];

    // validate required
    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }

    if (!isset($_FILES['identity_img']) || $_FILES['identity_img']['error'] !== 0) {
        $errors['file'] = "Identity Document Required.";
    }

    // if validation failed
    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['create_staff_inputs'] = $_POST;
        redirection_logic();
    }

    // check duplicates
    foreach ([
        ['identity_no', "identity_no"],
        ['first_names', "first_names"],
        ['emp_code', "emp_code"]
    ] as [$input, $column]) {
        $sql = "SELECT id FROM staff WHERE $column = ? AND shift_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $_POST[$input], $_POST['shift_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->fetch_assoc()) {
            $errors["{$column}_taken"] = ucfirst(str_replace('_', ' ', $column)) . " already registered for this branch.";
        }
    }

    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        redirection_logic();
    }

    // validate and upload file
    $file = $_FILES['identity_img'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'jfif', 'png', 'pdf'];

    if (!in_array($ext, $allowed)) {
        $_SESSION['errors_create'] = ['file_type' => "Invalid file type. Allowed: JPG, JPEG, JFIF, PNG, PDF."];
        redirection_logic();
    }

    $filename = uniqid("identity_", true) . "." . $ext;
    $upload_path = $_SERVER['DOCUMENT_ROOT'] . "/cnrbyte/uploads/staff/profile/{$filename}";

    move_uploaded_file($file['tmp_name'], $upload_path);

    $rank_info = get_rank_info($conn, $_POST['rank_id']);
    // insert row
    $sql = "INSERT INTO staff (
        vendor_id, branch_id, shift_id, identity_img,
        identity_no, first_names, last_name, emp_code,
        start_date, rank, occupation, rate, contract
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssssssss', $_POST['vendor_id'], $_POST['branch_id'], $_POST['shift_id'], $filename, $_POST['identity_no'], $_POST['first_names'], $_POST['last_name'], $_POST['emp_code'], $_POST['start_date'], $rank_info['rank_name'], $rank_info['occupation'], $rank_info['rate'], $_POST['contract']);
    $stmt->execute();

    $success['staff_created'] = "Staff created successfully.";
    $_SESSION['success_create'] = $success;
    redirection_logic();
}
?>
