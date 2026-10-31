<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_info']['id'])) { http_response_code(403); exit('Not authorised.'); }
if (!cnrbyte_csrf_validate()) { http_response_code(403); exit('Invalid or expired form token.'); }
$isVendor=!empty($_SESSION['vendor_info']['id']);
if ($isVendor) { $vendorIdCheck=(int)$_SESSION['vendor_info']['id']; $ownerId=(int)$_SESSION['user_info']['id']; $ownerStmt=$conn->prepare('SELECT id FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1'); $ownerStmt->bind_param('iii',$vendorIdCheck,$ownerId,$ownerId); $ownerStmt->execute(); $owned=(bool)$ownerStmt->get_result()->fetch_assoc(); $ownerStmt->close(); if(!$owned){unset($_SESSION['vendor_info']);http_response_code(403);exit('Vendor workspace access denied.');} }
$type=$isVendor?'vendor':'client';
$actorId=$isVendor?(int)$_SESSION['vendor_info']['id']:(int)$_SESSION['user_info']['id'];
function msg_action_redirect(int $orderId=0): void { header('Location: /cnrbyte/messages/dashboard.pge.php'.($orderId>0?'?order_id='.$orderId:'')); exit; }
$action=(string)($_POST['action']??'');
if($action==='mark_notification_read'){
    $notificationId=(int)($_POST['notification_id']??0);
    $stmt=$conn->prepare('UPDATE cnrbyte_notifications SET is_read=1,read_at=NOW() WHERE id=? AND recipient_type=? AND recipient_id=?');
    $stmt->bind_param('isi',$notificationId,$type,$actorId);$stmt->execute();$stmt->close();msg_action_redirect();
}
if($action==='mark_legacy_message_read'){
    $messageId=(int)($_POST['message_id']??0);
    $userId=(int)($_SESSION['user_info']['id']??0);
    $stmt=$conn->prepare('UPDATE messages SET is_read=1,status=\'read\' WHERE id=? AND receiver_id=?');
    $stmt->bind_param('ii',$messageId,$userId);$stmt->execute();$stmt->close();msg_action_redirect();
}
if($action!=='send_message'){http_response_code(400);exit('Unknown action.');}
$orderId=(int)($_POST['order_id']??0);$body=trim((string)($_POST['body']??''));
if($orderId<1||$body===''||mb_strlen($body)>5000) { $_SESSION['errors_create']=['message'=>'Select an order and enter a message of up to 5,000 characters.']; msg_action_redirect($orderId); }
if($isVendor){
    $stmt=$conn->prepare('SELECT id,buyer_id,order_ref FROM marketplace_orders WHERE id=? AND vendor_id=? LIMIT 1');
    $stmt->bind_param('ii',$orderId,$actorId);$stmt->execute();$order=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$order){http_response_code(403);exit('You cannot access this order conversation.');}
    $senderType='vendor';$senderId=$actorId;$recipientType='client';$recipientId=(int)$order['buyer_id'];
}else{
    $stmt=$conn->prepare('SELECT id,vendor_id,order_ref FROM marketplace_orders WHERE id=? AND buyer_id=? LIMIT 1');
    $stmt->bind_param('ii',$orderId,$actorId);$stmt->execute();$order=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$order){http_response_code(403);exit('You cannot access this order conversation.');}
    $senderType='client';$senderId=$actorId;$recipientType='vendor';$recipientId=(int)$order['vendor_id'];
}
$key='order:'.$orderId;$subject='Order '.$order['order_ref'];
$conn->begin_transaction();
try {
    $stmt=$conn->prepare('INSERT INTO cnrbyte_messages (conversation_key,sender_type,sender_id,recipient_type,recipient_id,subject,body,related_order_id) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->bind_param('ssisissi',$key,$senderType,$senderId,$recipientType,$recipientId,$subject,$body,$orderId);$stmt->execute();$stmt->close();
    $title='New order message';$note='A new message was sent about order '.$order['order_ref'];$url='/cnrbyte/messages/dashboard.pge.php?order_id='.$orderId;
    $stmt=$conn->prepare('INSERT INTO cnrbyte_notifications (recipient_type,recipient_id,notification_type,title,message,target_url) VALUES (?,?,?,?,?,?)');
    $notificationType='message';$stmt->bind_param('sissss',$recipientType,$recipientId,$notificationType,$title,$note,$url);$stmt->execute();$stmt->close();
    $conn->commit();
} catch(Throwable $e) { $conn->rollback(); error_log('CnrByte messaging error: '.$e->getMessage()); $_SESSION['errors_create']=['message'=>'The message could not be saved. Apply the messaging schema migration and try again.']; }
msg_action_redirect($orderId);
