<?php
declare(strict_types=1);

/** Save the supported client profile-image field without writing nonexistent identity columns. */
function create_personal_info(mysqli $conn, string $first_names, string $last_name, string $identity_no, string $img, string $user_id): bool {
    $clientId=(int)$user_id;
    if ($clientId<1 || $clientId !== (int)($_SESSION['user_info']['id'] ?? 0)) return false;
    $columns=[]; $result=$conn->query('SHOW COLUMNS FROM profiles');
    if ($result) { while($row=$result->fetch_assoc()) $columns[$row['Field']]=true; $result->free(); }
    if (!isset($columns['client_id'], $columns['profile_img'])) return false;
    $stmt=$conn->prepare('SELECT id FROM profiles WHERE client_id=? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i',$clientId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($row) { $profileId=(int)$row['id']; $stmt=$conn->prepare('UPDATE profiles SET profile_img=? WHERE id=? AND client_id=?'); $stmt->bind_param('sii',$img,$profileId,$clientId); }
    else { $stmt=$conn->prepare('INSERT INTO profiles (client_id,profile_img) VALUES (?,?)'); $stmt->bind_param('is',$clientId,$img); }
    $ok=$stmt->execute(); $stmt->close(); return $ok;
}
