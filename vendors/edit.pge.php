<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/conn.dbh.php';
if(empty($_SESSION['user_info']['id'])){header('Location: /cnrbyte/signup_login/signup_login.pge.php');exit;}
$vendorId=(int)($_SESSION['vendor_info']['id']??0);$clientId=(int)$_SESSION['user_info']['id'];
if($vendorId<1){header('Location: /cnrbyte/vendors/select.pge.php');exit;}
$stmt=$conn->prepare('SELECT id FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1');$stmt->bind_param('iii',$vendorId,$clientId,$clientId);$stmt->execute();$owned=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$owned){unset($_SESSION['vendor_info']);http_response_code(403);exit('You do not have access to this vendor workspace.');}
header('Location: /cnrbyte/vendors/profile.pge.php');exit;
