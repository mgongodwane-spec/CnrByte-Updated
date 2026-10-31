<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';

$errors = [];

if (isset($_POST['edit_financial_account'])) {
    $client_id = (int)($_SESSION['user_info']['id'] ?? 0);
    $account_id = (int)($_POST['account_id'] ?? 0);
    $account_name = trim((string)($_POST['account_name'] ?? ''));
    $account_type = trim((string)($_POST['account_type'] ?? ''));

    if ($client_id <= 0) $errors['login'] = 'Please log in first.';
    if ($account_id <= 0) $errors['account_id'] = 'Select account.';
    if ($account_name !== '' && (mb_strlen($account_name) > 120 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $account_name))) {
        $errors['account_name'] = 'Account name is too long or contains invalid characters.';
    }
    if ($account_type !== '' && !in_array($account_type, ['cash','bank','investment','credit'], true)) {
        $errors['account_type'] = 'Choose a valid account type.';
    }
    if ($account_name === '' && $account_type === '') {
        $errors['changes'] = 'Provide at least one change (name or type).';
    }

    if (!empty($errors)) {
        $_SESSION['errors_create'] = $errors;
        redirection_logic();
    }

    $check = $conn->prepare("SELECT id FROM financial_accounts WHERE id = ? AND client_id = ?");
    $check->bind_param("ii", $account_id, $client_id);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        $check->close();
        $_SESSION['errors_create']['account_owner'] = 'Invalid account selection.';
        redirection_logic();
    }
    $check->close();

    $sql = "UPDATE financial_accounts SET
            account_name = COALESCE(NULLIF(?, ''), account_name),
            account_type = COALESCE(NULLIF(?, ''), account_type)
            WHERE id = ? AND client_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $account_name, $account_type, $account_id, $client_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success_create']['financial_edit'] = 'Financial account updated.';
    redirection_logic();
}
