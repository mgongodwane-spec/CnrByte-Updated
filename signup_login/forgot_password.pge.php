<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_helpers.php';
function cnrbyte_reset_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$message = (string)($_SESSION['password_reset_message'] ?? '');
unset($_SESSION['password_reset_message']);
$error = (string)($_SESSION['password_reset_error'] ?? '');
unset($_SESSION['password_reset_error']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset password — CnrByte</title><link rel="stylesheet" href="/cnrbyte/css/main.css"><link rel="stylesheet" href="/cnrbyte/css/forms.css"><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"></head><body>
<header class="page-header"><div class="brand"><h1>CnrByte</h1></div><a href="/cnrbyte/signup_login/signup_login.pge.php">Back to login</a></header>
<main class="user_form"><form action="password_reset.request.run.php" method="post" class="input_form"><h1 class="form-title">Forgot your password?</h1><p>Enter the email address associated with your account. If an account matches, instructions will be sent.</p>
<?php if ($message !== ''): ?><p class="form-success" role="status"><?= cnrbyte_reset_escape($message) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="form-error" role="alert"><?= cnrbyte_reset_escape($error) ?></p><?php endif; ?>
<input type="hidden" name="csrf_token" value="<?= cnrbyte_reset_escape((string)($_SESSION['csrf_token'] ?? '')) ?>"><label for="email">Email address</label><input id="email" name="email" type="email" required maxlength="150" autocomplete="email"><button type="submit" class="form-btn">Send reset instructions</button></form></main></body></html>
