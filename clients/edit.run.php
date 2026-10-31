<?php
declare(strict_types=1);

/** Update the authenticated client's login/contact fields using the actual clients schema. */
function edit_login_info(mysqli $conn, string $username, string $email, string $cell_no, string $img, string $user_id): bool {
    $id=(int)$user_id;
    if ($id < 1 || $id !== (int)($_SESSION['user_info']['id'] ?? 0) || trim($username)==='' || !filter_var(trim($email), FILTER_VALIDATE_EMAIL) || trim($cell_no)==='') return false;
    $email=strtolower(trim($email));
    $stmt=$conn->prepare('SELECT id FROM clients WHERE (username=? OR LOWER(TRIM(email))=?) AND id<>? LIMIT 1');
    $stmt->bind_param('ssi',$username,$email,$id); $stmt->execute(); $duplicate=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($duplicate) return false;
    $stmt=$conn->prepare('UPDATE clients SET username=?, email=?, phone=?, profile_img=? WHERE id=?');
    $stmt->bind_param('ssssi',$username,$email,$cell_no,$img,$id); $ok=$stmt->execute(); $stmt->close();
    if ($ok && (int)($_SESSION['user_info']['id'] ?? 0)===$id) { $_SESSION['user_info']['username']=$username; $_SESSION['username']=$username; $_SESSION['user_info']['profile_img']=$img; }
    return $ok;
}

/** Passwords are always stored as hashes, never as plain text. */
function edit_client_password(mysqli $conn, string $new_pwd, string $user_id): bool {
    $id=(int)$user_id;
    if ($id<1 || $id !== (int)($_SESSION['user_info']['id'] ?? 0) || strlen($new_pwd)<12 || strlen($new_pwd)>4096) return false;
    $hash=password_hash($new_pwd, PASSWORD_DEFAULT);
    $stmt=$conn->prepare('UPDATE clients SET pass_word=? WHERE id=?');
    $stmt->bind_param('si',$hash,$id); $ok=$stmt->execute(); $stmt->close();
    return $ok;
}
