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
    $_SESSION['errors_login'] = ['csrf' => 'Your form expired. Please refresh the page and try again.'];
    cnrbyte_auth_redirect('signup_login.pge.php');
}

$identifier = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['pass_word'] ?? '');
if ($identifier === '' || $password === '' || strlen($identifier) > 150 || strlen($password) > 4096) {
    $_SESSION['errors_login'] = ['auth' => 'Incorrect username/email or password.'];
    cnrbyte_auth_redirect('signup_login.pge.php');
}
if (cnrbyte_auth_is_rate_limited($conn, $identifier) || cnrbyte_auth_is_rate_limited($conn, '__cnrbyte_login_ip_bucket__')) {
    $_SESSION['errors_login'] = ['throttle' => 'Too many unsuccessful attempts. Please wait 15 minutes before trying again.'];
    cnrbyte_auth_redirect('signup_login.pge.php');
}

try {
    $normalizedIdentifier = strtolower($identifier);
    $stmt = $conn->prepare('SELECT id, username, pass_word, email, rank, profile_img FROM clients WHERE username=? OR LOWER(TRIM(email))=? ORDER BY (username=?) DESC LIMIT 1');
    $stmt->bind_param('sss', $identifier, $normalizedIdentifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, (string)$user['pass_word'])) {
        cnrbyte_auth_record_failure($conn, $identifier);
        cnrbyte_auth_record_failure($conn, '__cnrbyte_login_ip_bucket__');
        $_SESSION['errors_login'] = ['auth' => 'Incorrect username/email or password.'];
        cnrbyte_auth_redirect('signup_login.pge.php');
    }

    if (password_needs_rehash((string)$user['pass_word'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehash = $conn->prepare('UPDATE clients SET pass_word=? WHERE id=?');
        $userId = (int)$user['id'];
        $rehash->bind_param('si', $newHash, $userId);
        $rehash->execute();
        $rehash->close();
    }

    session_regenerate_id(true);
    cnrbyte_auth_set_user_session($user);
    cnrbyte_auth_rotate_csrf();
    cnrbyte_auth_clear_failures($conn, $identifier);

    $rank = cnrbyte_auth_normalize_rank($user['rank'] ?? 'client');
    $_SESSION['last_regeneration'] = time();
    $_SESSION['current_dashboard'] = $rank === 'admin' ? 'admin_dashboard' : 'clients_dashboard';
    cnrbyte_auth_redirect(cnrbyte_auth_route_after_login($conn, (int)$user['id'], $rank));
} catch (mysqli_sql_exception $e) {
    error_log('CnrByte login database error: ' . $e->getMessage());
    $_SESSION['errors_login'] = ['general' => 'Login is temporarily unavailable. Please try again later.'];
    cnrbyte_auth_redirect('signup_login.pge.php');
}
