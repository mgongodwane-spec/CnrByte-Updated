<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) {
    $_SESSION['password_reset_error'] = 'Your form expired. Please try again.';
    cnrbyte_auth_redirect('forgot_password.pge.php');
}
$email = cnrbyte_auth_normalize_email((string)($_POST['email'] ?? ''));
// Apply an IP-scoped reset-request throttle independent of the submitted email.
if (cnrbyte_auth_is_rate_limited($conn, '__cnrbyte_password_reset_ip_bucket__')) {
    $_SESSION['password_reset_message'] = 'If an account matches that email address, password-reset instructions will be sent shortly.';
    cnrbyte_auth_redirect('forgot_password.pge.php');
}
cnrbyte_auth_record_failure($conn, '__cnrbyte_password_reset_ip_bucket__');
// Always use the same public response to avoid disclosing whether an email is registered.
$_SESSION['password_reset_message'] = 'If an account matches that email address, password-reset instructions will be sent shortly.';
if ($email !== '' && strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    try {
        $stmt = $conn->prepare('SELECT id, username, email FROM clients WHERE LOWER(TRIM(email))=? LIMIT 1');
        $stmt->bind_param('s', $email); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $clientId = (int)$user['id'];
            $delete = $conn->prepare('DELETE FROM cnrbyte_password_reset_tokens WHERE client_id=? AND used_at IS NULL');
            $delete->bind_param('i', $clientId); $delete->execute(); $delete->close();
            $insert = $conn->prepare('INSERT INTO cnrbyte_password_reset_tokens (client_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
            $insert->bind_param('is', $clientId, $tokenHash); $insert->execute(); $insert->close();

            $baseUrl = rtrim((string)(getenv('CNRBYTE_BASE_URL') ?: 'http://localhost/cnrbyte'), '/');
            $resetUrl = $baseUrl . '/signup_login/password_reset.pge.php?token=' . rawurlencode($token);
            $from = (string)(getenv('CNRBYTE_MAIL_FROM') ?: 'no-reply@localhost');
            $subject = 'CnrByte password reset';
            $body = "A password reset was requested for your CnrByte account.\n\nUse this one-time link within one hour:\n" . $resetUrl . "\n\nIf you did not request this, you can ignore this email.\n";
            $headers = "From: " . str_replace(["\r", "\n"], '', $from) . "\r\nContent-Type: text/plain; charset=UTF-8";
            if (!@mail((string)$user['email'], $subject, $body, $headers)) {
                error_log('CnrByte password reset email could not be sent. Configure PHP mail transport.');
            }
        }
    } catch (Throwable $e) {
        error_log('CnrByte password reset request failed: ' . $e->getMessage());
    }
}
cnrbyte_auth_redirect('forgot_password.pge.php');
