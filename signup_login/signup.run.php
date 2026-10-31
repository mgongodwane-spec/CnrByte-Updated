<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) {
    $_SESSION['errors_signup'] = ['csrf' => 'Your form expired. Please try again.'];
    cnrbyte_auth_redirect('signup_login.pge.php');
}

$username = trim((string)($_POST['username'] ?? ''));
$email = cnrbyte_auth_normalize_email((string)($_POST['email'] ?? ''));
$password = (string)($_POST['pass_word'] ?? '');
$confirmPassword = (string)($_POST['c_password'] ?? '');
$phone = trim((string)($_POST['phone'] ?? ''));
$errors = [];

if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
    $errors['username'] = 'Choose a username of 3–40 characters using letters, numbers, dots, underscores, or hyphens.';
}
if ($email === '' || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}
if (strlen($password) < 12 || strlen($password) > 4096) {
    $errors['password'] = 'Use a password between 12 and 4096 characters.';
}
if (!hash_equals($password, $confirmPassword)) {
    $errors['confirm'] = 'The passwords do not match.';
}
if (strlen($phone) > 50) {
    $errors['phone'] = 'The phone number is too long.';
}

if ($errors) {
    $_SESSION['errors_signup'] = $errors;
    cnrbyte_auth_redirect('signup_login.pge.php');
}

try {
    $check = $conn->prepare('SELECT id FROM clients WHERE username=? OR LOWER(TRIM(email))=? LIMIT 1');
    $check->bind_param('ss', $username, $email);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if ($exists) {
        $_SESSION['errors_signup'] = ['exists' => 'That username or email address is already registered.'];
        cnrbyte_auth_redirect('signup_login.pge.php');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Password hashing failed.');
    }
    // Public registration can only create a client account. Administrator provisioning is CLI-only.
    $rank = 'client';
    $stmt = $conn->prepare('INSERT INTO clients (username, pass_word, email, phone, rank, profile_img) VALUES (?, ?, ?, ?, ?, ?)');
    $profileImage = 'default.png';
    $stmt->bind_param('ssssss', $username, $hash, $email, $phone, $rank, $profileImage);
    $stmt->execute();
    $stmt->close();

    $_SESSION['signup_success'] = 'Registration successful. Please log in.';
    cnrbyte_auth_redirect('signup_login.pge.php?signup=success');
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        $_SESSION['errors_signup'] = ['exists' => 'That username or email address is already registered.'];
    } else {
        error_log('CnrByte signup database error: ' . $e->getMessage());
        $_SESSION['errors_signup'] = ['general' => 'Registration could not be completed. Please try again later.'];
    }
    cnrbyte_auth_redirect('signup_login.pge.php');
} catch (Throwable $e) {
    error_log('CnrByte signup error: ' . $e->getMessage());
    $_SESSION['errors_signup'] = ['general' => 'Registration could not be completed. Please try again later.'];
    cnrbyte_auth_redirect('signup_login.pge.php');
}
