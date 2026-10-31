<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) {
    $_SESSION['password_reset_error'] = 'Your form expired. Please try again.';
    cnrbyte_auth_redirect('forgot_password.pge.php');
}
$token = (string)($_POST['token'] ?? '');
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');
if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1 || strlen($password) < 12 || strlen($password) > 4096 || !hash_equals($password, $confirm)) {
    $_SESSION['password_reset_error'] = 'The reset link or password details are invalid. Passwords must match and be at least 12 characters.';
    cnrbyte_auth_redirect('password_reset.pge.php?token=' . rawurlencode($token));
}
try {
    $conn->begin_transaction();
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare('SELECT id, client_id FROM cnrbyte_password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE');
    $stmt->bind_param('s', $tokenHash); $stmt->execute(); $reset = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$reset) {
        $conn->rollback();
        $_SESSION['password_reset_error'] = 'This reset link is invalid, expired, or already used. Request a new link.';
        cnrbyte_auth_redirect('forgot_password.pge.php');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $clientId = (int)$reset['client_id'];
    $update = $conn->prepare('UPDATE clients SET pass_word=? WHERE id=?');
    $update->bind_param('si', $hash, $clientId); $update->execute(); $update->close();
    $tokenId = (int)$reset['id'];
    $mark = $conn->prepare('UPDATE cnrbyte_password_reset_tokens SET used_at=NOW() WHERE id=? AND used_at IS NULL');
    $mark->bind_param('i', $tokenId); $mark->execute(); $mark->close();
    $delete = $conn->prepare('DELETE FROM cnrbyte_password_reset_tokens WHERE client_id=? AND id<>?');
    $delete->bind_param('ii', $clientId, $tokenId); $delete->execute(); $delete->close();
    $conn->commit();
    $_SESSION = [];
    session_regenerate_id(true);
    cnrbyte_auth_rotate_csrf();
    $_SESSION['signup_success'] = 'Your password has been updated. Please log in with your new password.';
    cnrbyte_auth_redirect('signup_login.pge.php');
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('CnrByte password reset completion failed: ' . $e->getMessage());
    $_SESSION['password_reset_error'] = 'Your password could not be updated. Please request a new reset link or try again later.';
    cnrbyte_auth_redirect('forgot_password.pge.php');
}
