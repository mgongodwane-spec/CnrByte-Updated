<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the logout button to sign out.');
}
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid or expired security token. Refresh the page and try again.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
header('Location: /cnrbyte/signup_login/signup_login.pge.php');
exit;
