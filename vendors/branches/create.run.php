<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid or expired form token.'); }

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

$errors = [];
$success = [];

if(isset($_POST['create_new_branch'])){
    $inputs = [
        'vendor_id', 'branch_name', 'branch_code', 'phone', 'email'
    ];

    // validate required
    foreach ($inputs as $field) {
        if (empty($_POST[$field])) {
            $errors["empty_{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " required.";
        }
    }

    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['invalid_email'] = "Invalid email format.";
    }

    // if validation failed
    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        $_SESSION['create_branch_inputs'] = $_POST;
        header("Location: create.pge.php");
        exit;
    }

    // check duplicates
    foreach ([
        ['branch_code', "branch_code"],
        ] as [$input, $column]) {
        $stmt = $conn->prepare("SELECT id FROM branches WHERE $column = ?");
        if (!$stmt) {
            $_SESSION['errors_create'] = ['db' => 'Failed to prepare statement'];
            header("Location: create.pge.php");
            exit;
        }
        $stmt->bind_param('s', $_POST[$input]);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->fetch_assoc()) {
            $errors["{$column}_taken"] = "$column already registered.";
        }
        $stmt->close();
    }

    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        header("Location: create.pge.php?error_exists");
        exit;
    }

    // insert row
    $sql = "INSERT INTO branches (vendor_id, branch_name, branch_code, phone, email) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['errors_create'] = ['db' => 'Failed to prepare statement'];
        header("Location: create.pge.php");
        exit;
    }
    $stmt->bind_param('issss', $_POST['vendor_id'], $_POST['branch_name'], $_POST['branch_code'], $_POST['phone'], $_POST['email']);
    $stmt->execute();
    $stmt->close();

    header("Location: create.pge.php?branch_creation_success");
    $success['create_branch'] = "Branch created successfully.";
    $_SESSION['success_create'] = $success;
    exit;
}
?>
