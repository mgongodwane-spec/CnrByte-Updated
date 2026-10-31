<?php
declare(strict_types=1);

require_once __DIR__ . '/session.run.php';

function cnrbyte_auth_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function cnrbyte_auth_csrf_valid($submitted): bool
{
    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

/** Reject state-changing requests that are not POST requests with a valid session CSRF token. */
function cnrbyte_auth_require_post_csrf(): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }
    if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid or expired form token. Refresh the page and try again.');
    }
}

function cnrbyte_auth_rotate_csrf(): void
{
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

function cnrbyte_auth_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function cnrbyte_auth_normalize_rank(mixed $rank): string
{
    $value = strtolower(trim((string)$rank));
    if (in_array($value, ['admin', 'administrator'], true)) return 'admin';
    if (in_array($value, ['vendor', 'seller', 'merchant'], true)) return 'vendor';
    return 'client';
}

function cnrbyte_auth_set_user_session(array $user): void
{
    $rank = cnrbyte_auth_normalize_rank($user['rank'] ?? 'client');
    $id = (int)($user['id'] ?? 0);
    $username = (string)($user['username'] ?? '');
    $profileImage = (string)($user['profile_img'] ?? 'default.png');

    // Canonical session payload plus compatibility keys used by legacy pages.
    $_SESSION['user_info'] = [
        'id' => $id,
        'username' => $username,
        'rank' => $rank,
        'profile_img' => $profileImage !== '' ? $profileImage : 'default.png',
    ];
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $username;
    $_SESSION['rank'] = $rank;
    $_SESSION['user_rank'] = $rank;
    unset($_SESSION['vendor_info']);
}

function cnrbyte_auth_ip_hash(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $pepper = (string)(getenv('CNRBYTE_AUTH_PEPPER') ?: 'CHANGE-ME-CNRBYTE-AUTH-PEPPER-IN-PRODUCTION');
    return hash_hmac('sha256', $ip, $pepper);
}

function cnrbyte_auth_identifier_hash(string $identifier): string
{
    $pepper = (string)(getenv('CNRBYTE_AUTH_PEPPER') ?: 'CHANGE-ME-CNRBYTE-AUTH-PEPPER-IN-PRODUCTION');
    return hash_hmac('sha256', strtolower(trim($identifier)), $pepper);
}

/** Returns true when this identifier/IP pair is currently rate limited. */
function cnrbyte_auth_is_rate_limited(mysqli $conn, string $identifier): bool
{
    try {
        $identifierHash = cnrbyte_auth_identifier_hash($identifier);
        $ipHash = cnrbyte_auth_ip_hash();
        $stmt = $conn->prepare('SELECT attempts, window_started_at, blocked_until FROM cnrbyte_auth_attempts WHERE identifier_hash=? AND ip_hash=? LIMIT 1');
        $stmt->bind_param('ss', $identifierHash, $ipHash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return false;
        }
        if (!empty($row['blocked_until']) && strtotime((string)$row['blocked_until']) > time()) {
            return true;
        }
        if (strtotime((string)$row['window_started_at']) < time() - 900) {
            $reset = $conn->prepare('UPDATE cnrbyte_auth_attempts SET attempts=0, window_started_at=NOW(), blocked_until=NULL WHERE identifier_hash=? AND ip_hash=?');
            $reset->bind_param('ss', $identifierHash, $ipHash);
            $reset->execute();
            $reset->close();
        }
        return false;
    } catch (Throwable $e) {
        // The migration adds the throttle table. Keep login availability if an older DB is used,
        // while logging that deployment needs the migration.
        error_log('CnrByte auth throttling unavailable: ' . $e->getMessage());
        return false;
    }
}

function cnrbyte_auth_record_failure(mysqli $conn, string $identifier): void
{
    try {
        $identifierHash = cnrbyte_auth_identifier_hash($identifier);
        $ipHash = cnrbyte_auth_ip_hash();
        $stmt = $conn->prepare('SELECT attempts, window_started_at FROM cnrbyte_auth_attempts WHERE identifier_hash=? AND ip_hash=? LIMIT 1');
        $stmt->bind_param('ss', $identifierHash, $ipHash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $attempts = 1;
            $insert = $conn->prepare('INSERT INTO cnrbyte_auth_attempts (identifier_hash, ip_hash, attempts, window_started_at, blocked_until) VALUES (?, ?, 1, NOW(), NULL) ON DUPLICATE KEY UPDATE attempts=attempts+1');
            $insert->bind_param('ss', $identifierHash, $ipHash);
            $insert->execute();
            $insert->close();
            return;
        }

        $inWindow = strtotime((string)$row['window_started_at']) >= time() - 900;
        $attempts = $inWindow ? ((int)$row['attempts'] + 1) : 1;
        $blockedUntil = $attempts >= 8 ? date('Y-m-d H:i:s', time() + 900) : null;
        if ($inWindow) {
            $update = $conn->prepare('UPDATE cnrbyte_auth_attempts SET attempts=?, blocked_until=? WHERE identifier_hash=? AND ip_hash=?');
        } else {
            $update = $conn->prepare('UPDATE cnrbyte_auth_attempts SET attempts=?, window_started_at=NOW(), blocked_until=? WHERE identifier_hash=? AND ip_hash=?');
        }
        $update->bind_param('isss', $attempts, $blockedUntil, $identifierHash, $ipHash);
        $update->execute();
        $update->close();
    } catch (Throwable $e) {
        error_log('CnrByte auth failure tracking unavailable: ' . $e->getMessage());
    }
}

function cnrbyte_auth_clear_failures(mysqli $conn, string $identifier): void
{
    try {
        $identifierHash = cnrbyte_auth_identifier_hash($identifier);
        $ipHash = cnrbyte_auth_ip_hash();
        $stmt = $conn->prepare('DELETE FROM cnrbyte_auth_attempts WHERE identifier_hash=? AND ip_hash=?');
        $stmt->bind_param('ss', $identifierHash, $ipHash);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('CnrByte auth failure reset unavailable: ' . $e->getMessage());
    }
}

function cnrbyte_auth_route_after_login(mysqli $conn, int $clientId, string $rank): string
{
    if ($rank === 'admin') {
        $_SESSION['current_dashboard'] = 'admin_dashboard';
        unset($_SESSION['vendor_info'], $_SESSION['branch_info'], $_SESSION['shift_info'], $_SESSION['staff_info']);
        return '/cnrbyte/admin/dashboard.pge.php';
    }

    try {
        $stmt = $conn->prepare('SELECT id, client_id, reg_name, reg_no, tax_no, status FROM vendors WHERE (client_id=? OR user_id=?) ORDER BY id');
        $stmt->bind_param('ii', $clientId, $clientId);
        $stmt->execute();
        $result = $stmt->get_result();
        $vendors = [];
        while ($row = $result->fetch_assoc()) {
            $vendors[(int)$row['id']] = $row;
        }
        $stmt->close();

        if (count($vendors) === 1) {
            $vendor = reset($vendors);
            $_SESSION['vendor_info'] = [
                'id' => (int)$vendor['id'],
                'client_id' => (int)$vendor['client_id'],
                'reg_name' => (string)$vendor['reg_name'],
                'reg_no' => (string)($vendor['reg_no'] ?? ''),
                'tax_no' => (string)($vendor['tax_no'] ?? ''),
                'status' => (string)($vendor['status'] ?? 'pending'),
            ];
            $_SESSION['current_dashboard'] = 'vendors_dashboard';
            return '/cnrbyte/vendors/dashboard.pge.php';
        }
        if (count($vendors) > 1) {
            $_SESSION['current_dashboard'] = 'vendors_dashboard';
            return '/cnrbyte/vendors/select.pge.php';
        }
    } catch (Throwable $e) {
        // The vendor migration may not have been applied yet. Keep the client workspace available.
        error_log('CnrByte vendor post-login routing unavailable: ' . $e->getMessage());
    }
    unset($_SESSION['vendor_info'], $_SESSION['branch_info'], $_SESSION['shift_info'], $_SESSION['staff_info']);
    $_SESSION['current_dashboard'] = 'clients_dashboard';
    return '/cnrbyte/clients/dashboard.pge.php';
}
