<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';

$errors = [];

if (isset($_POST['create_financial_account'])) {
    $client_id = (int)($_SESSION['user_info']['id'] ?? 0);
    $account_name = trim((string)($_POST['account_name'] ?? ''));
    $account_type = trim((string)($_POST['account_type'] ?? ''));
    $currency = strtoupper(trim((string)($_POST['currency'] ?? 'ZAR')));
    $opening_raw = (string)($_POST['opening_balance'] ?? '0');
    $opening_balance = is_numeric($opening_raw) ? (float)$opening_raw : NAN;
    $allowedAccountTypes = ['cash', 'bank', 'investment', 'credit'];
    $allowedCurrencies = ['ZAR', 'USD', 'EUR', 'GBP'];

    if ($client_id <= 0) $errors['login'] = 'Please log in first.';
    if ($account_name === '') $errors['account_name'] = 'Account name required.';
    if (!in_array($account_type, $allowedAccountTypes, true)) $errors['account_type'] = 'Choose a valid account type.';
    if (!in_array($currency, $allowedCurrencies, true)) $errors['currency'] = 'Choose a supported currency.';
    if (!is_finite($opening_balance) || abs($opening_balance) > 1000000000000) $errors['opening_balance'] = 'Enter a valid opening balance.';

    if (!empty($errors)) {
        $_SESSION['errors_create'] = $errors;
        redirection_logic();
    }

    $stmt = $conn->prepare("INSERT INTO financial_accounts (client_id, account_name, account_type, currency, opening_balance) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssd", $client_id, $account_name, $account_type, $currency, $opening_balance);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success_create']['financial_account'] = 'Financial account created.';
    redirection_logic();
}

if (isset($_POST['create_financial_transaction'])) {
    $client_id = (int)($_SESSION['user_info']['id'] ?? 0);
    $account_id = (int)($_POST['account_id'] ?? 0);
    $txn_type = trim((string)($_POST['txn_type'] ?? ''));
    $allowedTxnTypes = ['deposit', 'withdrawal', 'expense', 'income'];
    $description = trim((string)($_POST['description'] ?? ''));
    $amount_raw = (string)($_POST['amount'] ?? '0');
    $amount_input = is_numeric($amount_raw) ? (float)$amount_raw : NAN;

    if ($client_id <= 0) $errors['login'] = 'Please log in first.';
    if ($account_id <= 0) $errors['account_id'] = 'Select account.';
    if (!in_array($txn_type, $allowedTxnTypes, true)) $errors['txn_type'] = 'Select a valid transaction type.';
    if (!is_finite($amount_input) || $amount_input <= 0 || $amount_input > 1000000000000) $errors['amount'] = 'Enter a valid positive amount.';

    $negative_types = ['withdrawal', 'expense'];
    $amount = in_array($txn_type, $negative_types, true) ? ($amount_input * -1) : $amount_input;

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

    $stmt = $conn->prepare("INSERT INTO financial_transactions (account_id, txn_type, amount, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isds", $account_id, $txn_type, $amount, $description);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success_create']['financial_txn'] = 'Financial transaction saved.';
    redirection_logic();
}
