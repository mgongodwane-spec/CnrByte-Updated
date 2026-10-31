<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';

function cnr_stmt_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function cnr_stmt_owned_type(mysqli $conn, int $typeId, int $clientId, array $allowedTypes): ?array
{
    if ($typeId < 1 || $clientId < 1) return null;
    $stmt = $conn->prepare('SELECT id, type, type_name, client_id FROM types WHERE id = ? AND client_id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('ii', $typeId, $clientId);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row || !in_array((string)$row['type'], $allowedTypes, true)) return null;
    return $row;
}

function cnr_stmt_redirect_error(string $key, string $message): void
{
    $_SESSION['errors_create'] = [$key => $message];
    redirection_logic();
}

$clientId = (int)($_SESSION['user_info']['id'] ?? 0);
$postedVendorId = (int)($_POST['vendor_id'] ?? 0);
$postedBranchId = (int)($_POST['branch_id'] ?? 0);
$currentVendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
$currentBranchId = (int)($_SESSION['branch_info']['id'] ?? 0);
if ($clientId < 1 || $postedVendorId !== $currentVendorId || $postedBranchId < 1 || $postedBranchId !== $currentBranchId) {
    http_response_code(403);
    exit('The selected vendor or branch does not match your workspace.');
}
$branchCheck = $conn->prepare('SELECT id FROM branches WHERE id = ? AND vendor_id = ? LIMIT 1');
if (!$branchCheck) { http_response_code(500); exit('Unable to verify branch access.'); }
$branchCheck->bind_param('ii', $postedBranchId, $currentVendorId);
$branchCheck->execute();
$branchOwned = (bool)$branchCheck->get_result()->fetch_assoc();
$branchCheck->close();
if (!$branchOwned) { http_response_code(403); exit('Branch access denied.'); }

if (isset($_POST['create_income_expense'])) {
    $accountId = filter_var($_POST['account_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $date = trim((string)($_POST['trans_date'] ?? ''));
    if ($accountId === false || !is_numeric($amountRaw) || !is_finite((float)$amountRaw) || (float)$amountRaw <= 0 || (float)$amountRaw > 9999999999.99 || !cnr_stmt_valid_date($date)) {
        cnr_stmt_redirect_error('input', 'Choose an account, enter a positive valid amount, and provide a valid transaction date.');
    }
    $account = cnr_stmt_owned_type($conn, (int)$accountId, $clientId, ['income', 'expense']);
    if (!$account) cnr_stmt_redirect_error('account', 'Choose an income or expense account belonging to your account.');

    $amount = round((float)$amountRaw, 2);
    $table = $account['type'] === 'income' ? 'crj' : 'cpj';
    $details = $account['type'] === 'income' ? 'Income received: ' . $account['type_name'] : 'Expense paid: ' . $account['type_name'];
    $duplicate = $conn->prepare("SELECT id FROM {$table} WHERE vendor_id = ? AND branch_id = ? AND acc_id = ? AND bank = ? AND trans_date = ? LIMIT 1");
    if (!$duplicate) cnr_stmt_redirect_error('db', 'Could not validate duplicate transactions.');
    $duplicate->bind_param('iiids', $currentVendorId, $currentBranchId, $accountId, $amount, $date);
    $duplicate->execute();
    $exists = (bool)$duplicate->get_result()->fetch_assoc();
    $duplicate->close();
    if ($exists) cnr_stmt_redirect_error('taken', 'This transaction already exists for the selected branch and date.');

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO {$table} (vendor_id, branch_id, acc_id, details, bank, account_type, account_name, amount, trans_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new RuntimeException('Unable to prepare income/expense insert.');
        $stmt->bind_param('iiisdssds', $currentVendorId, $currentBranchId, $accountId, $details, $amount, $account['type'], $account['type_name'], $amount, $date);
        if (!$stmt->execute()) throw new RuntimeException('Unable to save income/expense transaction.');
        $stmt->close();
        $conn->commit();
        $_SESSION['success_create']['acc_created'] = 'Income or expense transaction saved successfully.';
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('CnrByte income/expense statement save failed: ' . $e->getMessage());
        cnr_stmt_redirect_error('db', 'The transaction could not be saved. Please try again.');
    }
    redirection_logic();
}

if (isset($_POST['create_long_term_bal_stmt'])) {
    $accountId = filter_var($_POST['account_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $costRaw = trim((string)($_POST['cost'] ?? ''));
    $rateRaw = trim((string)($_POST['rate'] ?? ''));
    $serialNo = trim((string)($_POST['serial_no'] ?? ''));
    $date = trim((string)($_POST['date_obtained'] ?? ''));
    $lifeSpan = trim((string)($_POST['life_span'] ?? ''));
    if ($accountId === false || !is_numeric($costRaw) || !is_finite((float)$costRaw) || (float)$costRaw <= 0 || (float)$costRaw > 9999999999.99 || !is_numeric($rateRaw) || !is_finite((float)$rateRaw) || abs((float)$rateRaw) > 100 || !cnr_stmt_valid_date($date) || $serialNo === '' || mb_strlen($serialNo) > 180 || $lifeSpan === '' || mb_strlen($lifeSpan) > 100) {
        cnr_stmt_redirect_error('input', 'Enter a valid account, positive cost, rate between -100 and 100, serial/account number, date and lifespan.');
    }
    $account = cnr_stmt_owned_type($conn, (int)$accountId, $clientId, ['fixed_asset', 'fixed_liability']);
    if (!$account) cnr_stmt_redirect_error('account', 'Choose a fixed asset or fixed liability belonging to your account.');

    $cost = round((float)$costRaw, 2);
    $rate = round((float)$rateRaw, 4);
    if ($account['type'] === 'fixed_liability' && $rate < 0) cnr_stmt_redirect_error('rate', 'Interest rate cannot be negative for a liability.');
    $table = $account['type'] === 'fixed_liability' ? 'crj' : 'cpj';
    $details = $account['type'] === 'fixed_liability' ? 'Liability created: ' . $account['type_name'] : 'Asset acquired: ' . $account['type_name'];

    $duplicateJournal = $conn->prepare("SELECT id FROM {$table} WHERE vendor_id = ? AND branch_id = ? AND acc_id = ? AND bank = ? AND trans_date = ? LIMIT 1");
    if (!$duplicateJournal) cnr_stmt_redirect_error('db', 'Could not validate duplicate asset/liability entries.');
    $duplicateJournal->bind_param('iiids', $currentVendorId, $currentBranchId, $accountId, $cost, $date);
    $duplicateJournal->execute();
    $journalExists = (bool)$duplicateJournal->get_result()->fetch_assoc();
    $duplicateJournal->close();
    if ($journalExists) cnr_stmt_redirect_error('taken', 'This asset/liability entry already exists for the selected branch and date.');

    $duplicateBalance = $conn->prepare('SELECT id FROM bal_stmt WHERE vendor_id = ? AND branch_id = ? AND acc_id = ? AND cost = ? AND date_obtained = ? LIMIT 1');
    if (!$duplicateBalance) cnr_stmt_redirect_error('db', 'Could not validate duplicate asset/liability records.');
    $duplicateBalance->bind_param('iiids', $currentVendorId, $currentBranchId, $accountId, $cost, $date);
    $duplicateBalance->execute();
    $balanceExists = (bool)$duplicateBalance->get_result()->fetch_assoc();
    $duplicateBalance->close();
    if ($balanceExists) cnr_stmt_redirect_error('taken', 'This asset/liability record already exists for the selected branch and date.');

    try {
        $conn->begin_transaction();
        $journal = $conn->prepare("INSERT INTO {$table} (vendor_id, branch_id, acc_id, details, bank, account_type, account_name, amount, trans_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$journal) throw new RuntimeException('Unable to prepare asset/liability journal insert.');
        $journal->bind_param('iiisdssds', $currentVendorId, $currentBranchId, $accountId, $details, $cost, $account['type'], $account['type_name'], $cost, $date);
        if (!$journal->execute()) throw new RuntimeException('Unable to save asset/liability journal entry.');
        $journal->close();

        $balance = $conn->prepare('INSERT INTO bal_stmt (vendor_id, branch_id, acc_id, date_obtained, account_name, cost, rate, serial_no, life_span) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$balance) throw new RuntimeException('Unable to prepare balance statement insert.');
        $balance->bind_param('iiissddss', $currentVendorId, $currentBranchId, $accountId, $date, $account['type_name'], $cost, $rate, $serialNo, $lifeSpan);
        if (!$balance->execute()) throw new RuntimeException('Unable to save balance statement entry.');
        $balance->close();
        $conn->commit();
        $_SESSION['success_create']['acc_created'] = 'Asset/liability record saved successfully.';
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('CnrByte asset/liability statement save failed: ' . $e->getMessage());
        cnr_stmt_redirect_error('db', 'The asset/liability record could not be saved. Please try again.');
    }
    redirection_logic();
}

if (isset($_POST['create_equity'])) {
    $shareTypeName = trim((string)($_POST['share_type'] ?? ''));
    $shareAction = trim((string)($_POST['share_action'] ?? ''));
    $sharesRaw = trim((string)($_POST['num_of_shares'] ?? ''));
    $parValueRaw = trim((string)($_POST['par_value'] ?? ''));
    $issueDate = trim((string)($_POST['issue_date'] ?? ''));
    $maturityDate = trim((string)($_POST['maturity_date'] ?? ''));
    $shares = filter_var($sharesRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000000]]);
    if ($shareTypeName === '' || !in_array($shareAction, ['authorise', 'issue', 'pay_dividends'], true) || $shares === false || !is_numeric($parValueRaw) || !is_finite((float)$parValueRaw) || (float)$parValueRaw < 0 || (float)$parValueRaw > 9999999999.99 || !cnr_stmt_valid_date($issueDate) || !cnr_stmt_valid_date($maturityDate) || $maturityDate < $issueDate) {
        cnr_stmt_redirect_error('input', 'Enter a valid share type, action, number of shares, par value, and dates.');
    }

    $typeStmt = $conn->prepare("SELECT id, type_name FROM types WHERE client_id = ? AND type = 'share_type' AND type_name = ? LIMIT 1");
    if (!$typeStmt) cnr_stmt_redirect_error('db', 'Could not validate the share type.');
    $typeStmt->bind_param('is', $clientId, $shareTypeName);
    $typeStmt->execute();
    $shareType = $typeStmt->get_result()->fetch_assoc();
    $typeStmt->close();
    if (!$shareType) cnr_stmt_redirect_error('share_type', 'Choose a valid share type belonging to your account.');

    $shareTypeId = (int)$shareType['id'];
    $parValue = round((float)$parValueRaw, 2);
    $duplicate = $conn->prepare('SELECT id FROM shares WHERE vendor_id = ? AND branch_id = ? AND acc_id = ? AND share_type = ? AND issue_date = ? LIMIT 1');
    if (!$duplicate) cnr_stmt_redirect_error('db', 'Could not validate duplicate share records.');
    $duplicate->bind_param('iiiss', $currentVendorId, $currentBranchId, $shareTypeId, $shareType['type_name'], $issueDate);
    $duplicate->execute();
    $exists = (bool)$duplicate->get_result()->fetch_assoc();
    $duplicate->close();
    if ($exists) cnr_stmt_redirect_error('taken', 'A share record of this type already exists for that issue date and branch.');

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare('INSERT INTO shares (vendor_id, branch_id, acc_id, share_type, share_action, num_of_shares, par_value, issue_date, maturity_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) throw new RuntimeException('Unable to prepare share insert.');
        $stmt->bind_param('iiissidss', $currentVendorId, $currentBranchId, $shareTypeId, $shareType['type_name'], $shareAction, $shares, $parValue, $issueDate, $maturityDate);
        if (!$stmt->execute()) throw new RuntimeException('Unable to save share record.');
        $stmt->close();
        $conn->commit();
        $_SESSION['success_create']['acc_created'] = 'Share record saved successfully.';
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('CnrByte share statement save failed: ' . $e->getMessage());
        cnr_stmt_redirect_error('db', 'The share record could not be saved. Please try again.');
    }
    redirection_logic();
}

http_response_code(400);
exit('Unsupported statement action.');
