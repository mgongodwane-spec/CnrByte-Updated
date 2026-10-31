<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, max-age=0');
$token = (string)($_GET['token'] ?? '');
$valid = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
if ($valid) {
    try {
        $tokenHash = hash('sha256', $token);
        $stmt = $conn->prepare('SELECT id FROM cnrbyte_password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
        $stmt->bind_param('s', $tokenHash); $stmt->execute(); $valid = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
    } catch (Throwable $e) { $valid = false; error_log('CnrByte reset page validation failed: ' . $e->getMessage()); }
}
$error = (string)($_SESSION['password_reset_error'] ?? ''); unset($_SESSION['password_reset_error']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Choose a new password — CnrByte</title><link rel="stylesheet" href="/cnrbyte/css/main.css"><link rel="stylesheet" href="/cnrbyte/css/forms.css"><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"></head><body>
<header class="page-header"><div class="brand"><h1>CnrByte</h1></div><a href="/cnrbyte/signup_login/signup_login.pge.php">Back to login</a></header><main class="user_form"><section class="input_form"><h1 class="form-title">Choose a new password</h1>
<?php if (!$valid): ?><p class="form-error" role="alert">This password-reset link is invalid, expired, or already used. Request a new link.</p><a href="/cnrbyte/signup_login/forgot_password.pge.php">Request another reset link</a>
<?php else: ?><?php if ($error !== ''): ?><p class="form-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?><form action="password_reset.run.php" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><label for="password">New password</label><input id="password" name="password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required><button class="form-btn" type="submit">Update password</button></form><?php endif; ?>
</section></main></body></html>
