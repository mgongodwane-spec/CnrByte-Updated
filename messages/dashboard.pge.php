<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';
if (empty($_SESSION['user_info']['id'])) { header('Location: /cnrbyte/signup_login/signup_login.pge.php'); exit; }
$isVendor = !empty($_SESSION['vendor_info']['id']);
if ($isVendor) {
    $vendorIdCheck=(int)$_SESSION['vendor_info']['id']; $ownerId=(int)$_SESSION['user_info']['id'];
    $ownerStmt=$conn->prepare('SELECT id,reg_name FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1'); $ownerStmt->bind_param('iii',$vendorIdCheck,$ownerId,$ownerId); $ownerStmt->execute(); $ownedVendor=$ownerStmt->get_result()->fetch_assoc(); $ownerStmt->close();
    if (!$ownedVendor) { unset($_SESSION['vendor_info']); header('Location: /cnrbyte/vendors/select.pge.php'); exit; }
}
$_SESSION['current_dashboard'] = 'messages_dashboard';
$actorType = $isVendor ? 'vendor' : 'client';
$actorId = $isVendor ? (int)$_SESSION['vendor_info']['id'] : (int)$_SESSION['user_info']['id'];
$userId = (int)$_SESSION['user_info']['id'];
$selectedOrder = (int)($_GET['order_id'] ?? 0);
if ($isVendor) {
    $stmt=$conn->prepare('SELECT id,order_ref,buyer_id,buyer_username,vendor_name,created_at FROM marketplace_orders WHERE vendor_id=? ORDER BY created_at DESC LIMIT 100');
} else {
    $stmt=$conn->prepare('SELECT id,order_ref,buyer_id,buyer_username,vendor_id,vendor_name,created_at FROM marketplace_orders WHERE buyer_id=? ORDER BY created_at DESC LIMIT 100');
}
$stmt->bind_param('i',$actorId);$stmt->execute();$orders=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
if ($selectedOrder===0 && $orders) $selectedOrder=(int)$orders[0]['id'];
$selected=null;
foreach($orders as $o){if((int)$o['id']===$selectedOrder){$selected=$o;break;}}
$messages=[];
if($selected){
    $key='order:'.$selectedOrder;
    $stmt=$conn->prepare('SELECT sender_type,sender_id,recipient_type,recipient_id,body,created_at,is_read FROM cnrbyte_messages WHERE conversation_key=? ORDER BY created_at ASC LIMIT 300');
    $stmt->bind_param('s',$key);$stmt->execute();$messages=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    $stmt=$conn->prepare('UPDATE cnrbyte_messages SET is_read=1,read_at=NOW() WHERE conversation_key=? AND recipient_type=? AND recipient_id=?');
    $stmt->bind_param('ssi',$key,$actorType,$actorId);$stmt->execute();$stmt->close();
}
$stmt=$conn->prepare('SELECT id,title,message,target_url,is_read,created_at FROM cnrbyte_notifications WHERE recipient_type=? AND recipient_id=? ORDER BY created_at DESC LIMIT 50');
$stmt->bind_param('si',$actorType,$actorId);$stmt->execute();$notifications=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
$stmt=$conn->prepare('SELECT id,sender_id,sender_name,subject,message,is_read,created_at FROM messages WHERE receiver_id=? ORDER BY created_at DESC LIMIT 50');
$stmt->bind_param('i',$userId);$stmt->execute();$legacyMessages=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
function msg_h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CnrByte Messages</title><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"></head><body>
<?php require $_SERVER['DOCUMENT_ROOT'].'/cnrbyte/includes/show/header.php'; ?>
<main class="cnr-messages-layout">
<section class="cnr-form-card"><h2>Messages</h2><p>Conversations are available for marketplace orders associated with your account.</p><?php if (!$isVendor): ?><p><a class="cnr-header-link" href="/cnrbyte/messages/chat.pge.php">Open direct chats</a></p><?php else: ?><p>Use the order conversations below to chat with customers and reply to their messages.</p><p><a class="cnr-header-link" href="/cnrbyte/messages/vendor_chat.pge.php">Open customer direct chats</a></p><?php endif; ?>
<?php if(!$orders): ?><p class="cnr-empty-state">No order-linked conversations are available yet.</p><?php else: ?>
<div class="cnr-order-list"><?php foreach($orders as $o): ?><a class="cnr-order-link <?= ((int)$o['id']===$selectedOrder?'is-active':'') ?>" href="?order_id=<?= (int)$o['id'] ?>"><strong><?= msg_h($o['order_ref']) ?></strong><span><?= msg_h($isVendor ? ($o['buyer_username'] ?: ('Client #'.$o['buyer_id'])) : ($o['vendor_name'] ?: ('Vendor #'.$o['vendor_id']))) ?></span><small><?= msg_h($o['created_at']) ?></small></a><?php endforeach; ?></div>
<?php endif; ?>
</section>
<section class="cnr-form-card"><h2>Conversation</h2>
<?php if(!$selected): ?><p class="cnr-empty-state">Choose an order to view its conversation.</p><?php else: ?>
<div class="cnr-message-thread"><?php if(!$messages): ?><p class="cnr-empty-state">No messages yet. Send the first message below.</p><?php endif; foreach($messages as $m): $mine=$m['sender_type']===$actorType && (int)$m['sender_id']===$actorId; ?><article class="cnr-message <?= $mine?'is-mine':'' ?>"><div class="cnr-message-meta"><?= $mine?'You':msg_h(ucfirst($m['sender_type'])) ?> · <?= msg_h($m['created_at']) ?></div><p><?= nl2br(msg_h($m['body'])) ?></p></article><?php endforeach; ?></div>
<form method="post" action="/cnrbyte/messages/message.run.php"><input type="hidden" name="csrf_token" value="<?= msg_h($_SESSION['csrf_token']??'') ?>"><input type="hidden" name="action" value="send_message"><input type="hidden" name="order_id" value="<?= (int)$selectedOrder ?>"><label>Message<textarea name="body" rows="4" maxlength="5000" required placeholder="Write a message about this order"></textarea></label><button class="cnr-button" type="submit">Send message</button></form>
<?php endif; ?></section>
<section class="cnr-form-card" id="notifications"><h2>Notifications</h2><?php if(!$notifications): ?><p class="cnr-empty-state">No notifications yet.</p><?php else: ?><div class="cnr-notification-list"><?php foreach($notifications as $n): ?><article class="cnr-notification <?= !(int)$n['is_read']?'is-unread':'' ?>"><div><strong><?= msg_h($n['title']) ?></strong><p><?= msg_h($n['message']) ?></p><small><?= msg_h($n['created_at']) ?></small><?php if(!empty($n['target_url'])): ?> <a href="<?= msg_h($n['target_url']) ?>">Open</a><?php endif; ?></div><?php if(!(int)$n['is_read']): ?><form method="post" action="/cnrbyte/messages/message.run.php"><input type="hidden" name="csrf_token" value="<?= msg_h($_SESSION['csrf_token']??'') ?>"><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>"><button class="cnr-button cnr-button-secondary">Mark read</button></form><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
<h3>Account messages</h3>
<?php if (empty($legacyMessages)): ?><p class="cnr-empty-state">No account messages.</p><?php else: ?>
<div class="cnr-notification-list"><?php foreach ($legacyMessages as $legacy): ?><article class="cnr-notification <?= !(int)$legacy['is_read']?'is-unread':'' ?>"><div><strong><?= msg_h($legacy['subject'] ?: 'Account message') ?></strong><p><?= msg_h($legacy['message']) ?></p><small>From <?= msg_h($legacy['sender_name'] ?: ('Client #'.(int)$legacy['sender_id'])) ?> · <?= msg_h($legacy['created_at']) ?></small><p><a href="/cnrbyte/messages/chat.pge.php?contact_id=<?= (int)$legacy['sender_id'] ?>">Open chat / reply</a></p></div><?php if (!(int)$legacy['is_read']): ?><form method="post" action="/cnrbyte/messages/message.run.php"><input type="hidden" name="csrf_token" value="<?= msg_h($_SESSION['csrf_token']??'') ?>"><input type="hidden" name="action" value="mark_legacy_message_read"><input type="hidden" name="message_id" value="<?= (int)$legacy['id'] ?>"><button class="cnr-button cnr-button-secondary">Mark read</button></form><?php endif; ?></article><?php endforeach; ?></div>
<?php endif; ?>
</section>
</main></body></html>
