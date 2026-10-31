<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid or expired form token.'); }



require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

if(isset($_POST["create_new_shift"])){
    $inputs = [
        'vendor_id', 'branch_id', 'shift_name'
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
        $_SESSION['create_shift_inputs'] = $_POST;
        header("Location: create.pge.php?error_empty");
        exit;
    }

    // check duplicates
    foreach ([
        ['shift_name', "shift_name"],
    ] as [$input, $column]) {
        $stmt = $conn->prepare("SELECT id FROM shifts WHERE $column = ? AND branch_id = ?");
        if (!$stmt) {
            $_SESSION['errors_create'] = ['db' => 'Failed to prepare statement'];
            header("Location: create.pge.php?error");
            exit;
        }
        $stmt->bind_param('si', $_POST[$input], $_POST['branch_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->fetch_assoc()) {
            $errors["{$column}_taken"] = ucfirst(str_replace('_', ' ', $column)) . " already registered for this branch.";
        }
        $stmt->close();
    }

    if ($errors) {
        $_SESSION['errors_create'] = $errors;
        header("Location: create.pge.php?error_taken");
        exit;
    }

    // insert row
    $sql = "INSERT INTO shifts (vendor_id, branch_id, shift_name) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['errors_create'] = ['db' => 'Failed to prepare statement'];
        header("Location: create.pge.php?error");
        exit;
    }
    $stmt->bind_param('iis', $_POST['vendor_id'], $_POST['branch_id'], $_POST['shift_name']);
    $stmt->execute();
    $stmt->close();

    header("Location: create.pge.php?shift_creation_success");
    $success['shift_created'] = "Shift created successfully.";
    $_SESSION['success_create'] = $success;
    exit;
}
?>
