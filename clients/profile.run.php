<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/conn.dbh.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_SESSION['user_info']['id'])) {
    http_response_code(403); exit('Not authorised.');
}
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    http_response_code(403); exit('Invalid or expired form token.');
}
$clientId = (int)$_SESSION['user_info']['id'];
$username = trim((string)($_POST['username'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$currentPassword=(string)($_POST['current_password'] ?? '');
$newPassword=(string)($_POST['new_password'] ?? '');
$confirmPassword=(string)($_POST['confirm_password'] ?? '');
$passwordChange=($currentPassword!=='' || $newPassword!=='' || $confirmPassword!=='');
$limits = [
 'str_address'=>255, 'phy_suburb'=>150, 'phy_town'=>150, 'phy_province'=>150, 'phy_postal_code'=>50,
 'po_box'=>100, 'pos_suburb'=>150, 'pos_town'=>150, 'pos_province'=>150, 'pos_postal_code'=>50
];
$address = [];
foreach ($limits as $key=>$limit) {
    $address[$key] = trim((string)($_POST[$key] ?? ''));
    if (mb_strlen($address[$key]) > $limit) {
        $_SESSION['client_profile_error'] = 'One or more address fields are too long.';
        header('Location: /cnrbyte/clients/profile.pge.php'); exit;
    }
}
if (mb_strlen($username) < 3 || mb_strlen($username) > 100 || !preg_match('/^[\pL\pN_. -]+$/u', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email)>150 || $phone==='' || mb_strlen($phone)>50) {
    $_SESSION['client_profile_error'] = 'Enter a valid username, email address and contact number.';
    header('Location: /cnrbyte/clients/profile.pge.php'); exit;
}
try {
    $stmt=$conn->prepare('SELECT id FROM clients WHERE (username=? OR LOWER(TRIM(email))=?) AND id<>? LIMIT 1');
    $stmt->bind_param('ssi',$username,$email,$clientId); $stmt->execute(); $duplicate=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($duplicate) {
        $_SESSION['client_profile_error']='That username or email address is already used by another account.';
        header('Location: /cnrbyte/clients/profile.pge.php'); exit;
    }
    if ($passwordChange) {
        if ($currentPassword==='' || strlen($newPassword)<12 || strlen($newPassword)>4096 || $newPassword!==$confirmPassword) {
            $_SESSION['client_profile_error']='To change your password, enter your current password and matching new passwords of at least 12 characters.';
            header('Location: /cnrbyte/clients/profile.pge.php'); exit;
        }
        $stmt=$conn->prepare('SELECT pass_word FROM clients WHERE id=? LIMIT 1');
        $stmt->bind_param('i',$clientId); $stmt->execute(); $account=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$account || !password_verify($currentPassword,(string)$account['pass_word'])) {
            $_SESSION['client_profile_error']='Your current password was not correct. No changes were saved.';
            header('Location: /cnrbyte/clients/profile.pge.php'); exit;
        }
    }
    $conn->begin_transaction();
    $stmt=$conn->prepare('UPDATE clients SET username=?, email=?, phone=? WHERE id=?');
    $stmt->bind_param('sssi',$username,$email,$phone,$clientId); $stmt->execute(); $stmt->close();
    if ($passwordChange) {
        $passwordHash=password_hash($newPassword,PASSWORD_DEFAULT);
        $stmt=$conn->prepare('UPDATE clients SET pass_word=? WHERE id=?');
        $stmt->bind_param('si',$passwordHash,$clientId); $stmt->execute(); $stmt->close();
    }
    $stmt=$conn->prepare('SELECT id FROM profiles WHERE client_id=? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i',$clientId); $stmt->execute(); $existing=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($existing) {
        $profileId=(int)$existing['id'];
        $stmt=$conn->prepare('UPDATE profiles SET str_address=?, phy_suburb=?, phy_town=?, phy_province=?, phy_postal_code=?, po_box=?, pos_suburb=?, pos_town=?, pos_province=?, pos_postal_code=? WHERE id=? AND client_id=?');
        $stmt->bind_param('ssssssssssii',$address['str_address'],$address['phy_suburb'],$address['phy_town'],$address['phy_province'],$address['phy_postal_code'],$address['po_box'],$address['pos_suburb'],$address['pos_town'],$address['pos_province'],$address['pos_postal_code'],$profileId,$clientId);
    } else {
        $stmt=$conn->prepare('INSERT INTO profiles (client_id, str_address, phy_suburb, phy_town, phy_province, phy_postal_code, po_box, pos_suburb, pos_town, pos_province, pos_postal_code) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('issssssssss',$clientId,$address['str_address'],$address['phy_suburb'],$address['phy_town'],$address['phy_province'],$address['phy_postal_code'],$address['po_box'],$address['pos_suburb'],$address['pos_town'],$address['pos_province'],$address['pos_postal_code']);
    }
    $stmt->execute(); $stmt->close();
    $conn->commit();
    $_SESSION['user_info']['username']=$username; $_SESSION['username']=$username;
    if ($passwordChange) { session_regenerate_id(true); $_SESSION['csrf_token']=bin2hex(random_bytes(32)); }
    $_SESSION['client_profile_success']='Your profile and contact details have been saved.';
} catch (Throwable $e) {
    if ($conn->errno === 0) { /* connection remains usable; rollback below is safe */ }
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('CnrByte client profile update failed: '.$e->getMessage());
    $_SESSION['client_profile_error']='Your changes could not be saved. Please try again; if the issue continues, check the database schema.';
}
header('Location: /cnrbyte/clients/profile.pge.php'); exit;
