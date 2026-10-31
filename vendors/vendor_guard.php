<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
if (empty($_SESSION['user_info']['id'])) { header('Location: /cnrbyte/signup_login/signup_login.pge.php'); exit; }
$cnrGuardClientId=(int)$_SESSION['user_info']['id'];
$cnrGuardVendorId=(int)($_SESSION['vendor_info']['id']??0);
if ($cnrGuardVendorId<1) { header('Location: /cnrbyte/vendors/select.pge.php'); exit; }
$cnrGuardStmt=$conn->prepare('SELECT id,reg_name FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1');
$cnrGuardStmt->bind_param('iii',$cnrGuardVendorId,$cnrGuardClientId,$cnrGuardClientId); $cnrGuardStmt->execute(); $cnrGuardVendor=$cnrGuardStmt->get_result()->fetch_assoc(); $cnrGuardStmt->close();
if (!$cnrGuardVendor) { unset($_SESSION['vendor_info'],$_SESSION['branch_info'],$_SESSION['shift_info'],$_SESSION['staff_info']); http_response_code(403); exit('You do not have access to this vendor workspace.'); }
$_SESSION['vendor_info']['id']=$cnrGuardVendorId; $_SESSION['vendor_info']['reg_name']=(string)$cnrGuardVendor['reg_name'];
