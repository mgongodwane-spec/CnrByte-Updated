<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/conn.dbh.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_SESSION['user_info']['id'])) { http_response_code(403); exit('Not authorised.'); }
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); exit('Invalid or expired form token.'); }
$clientId=(int)$_SESSION['user_info']['id'];
if (isset($_POST['add_contact']) || isset($_POST['select_contact'])) {
    $contactId=(int)($_POST['contact_id'] ?? 0);
    if ($contactId<1 || $contactId===$clientId) { http_response_code(400); exit('Invalid contact.'); }
    $stmt=$conn->prepare('SELECT id,username,email,phone FROM clients WHERE id=? LIMIT 1');
    $stmt->bind_param('i',$contactId); $stmt->execute(); $contact=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$contact) { http_response_code(404); exit('Contact not found.'); }
    if (isset($_POST['add_contact'])) {
        $stmt=$conn->prepare('SELECT id FROM contacts WHERE client_id=? AND contact_id=? LIMIT 1');
        $stmt->bind_param('ii',$clientId,$contactId); $stmt->execute(); $exists=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$exists) {
            $stmt=$conn->prepare('INSERT INTO contacts (client_id,contact_id,contact_name,contact_username,contact_email,contact_phone) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('iissss',$clientId,$contactId,$contact['username'],$contact['username'],$contact['email'],$contact['phone']); $stmt->execute(); $stmt->close();
        }
    } else {
        $stmt=$conn->prepare('SELECT id FROM contacts WHERE client_id=? AND contact_id=? LIMIT 1');
        $stmt->bind_param('ii',$clientId,$contactId); $stmt->execute(); $saved=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$saved) { http_response_code(403); exit('Add this contact before opening a direct chat.'); }
    }
    $_SESSION['contact_info']=['contact_id'=>(int)$contact['id'],'contact_username'=>(string)$contact['username'],'contact_email'=>(string)$contact['email'],'contact_phone'=>(string)$contact['phone']];
    header('Location: /cnrbyte/messages/chat.pge.php'); exit;
}
if (!isset($_POST['send_message'])) { http_response_code(400); exit('Unknown action.'); }
$receiver=(int)($_POST['receiver_id'] ?? 0); $body=trim((string)($_POST['message'] ?? '')); $subject=trim((string)($_POST['subject'] ?? 'Direct Message'));
if ($receiver<1 || $receiver===$clientId || $body==='' || mb_strlen($body)>5000 || mb_strlen($subject)>180) { $_SESSION['direct_chat_error']='Enter a message of up to 5,000 characters.'; header('Location: /cnrbyte/messages/chat.pge.php'); exit; }
$stmt=$conn->prepare('SELECT c.id, c.username FROM clients c INNER JOIN contacts ct ON ct.contact_id=c.id WHERE ct.client_id=? AND ct.contact_id=? LIMIT 1');
$stmt->bind_param('ii',$clientId,$receiver); $stmt->execute(); $contact=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$contact) { http_response_code(403); exit('This recipient is not in your saved contacts.'); }
$stmt=$conn->prepare('SELECT username,email,phone FROM clients WHERE id=? LIMIT 1'); $stmt->bind_param('i',$clientId); $stmt->execute(); $sender=$stmt->get_result()->fetch_assoc(); $stmt->close();
$senderName=(string)($sender['username'] ?? 'Client'); $receiverName=(string)$contact['username'];
$stmt=$conn->prepare('INSERT INTO messages (sender_id,sender_name,receiver_id,receiver_name,subject,message,is_read,status) VALUES (?,?,?,?,?,?,0,\'unread\')');
$stmt->bind_param('isisss',$clientId,$senderName,$receiver,$receiverName,$subject,$body); $stmt->execute(); $stmt->close();
// Ensure the recipient can open the thread and reply even if the sender was not previously saved as a contact.
$stmt=$conn->prepare('SELECT id FROM contacts WHERE client_id=? AND contact_id=? LIMIT 1');
$stmt->bind_param('ii',$receiver,$clientId); $stmt->execute(); $reverseContact=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$reverseContact) {
    $stmt=$conn->prepare('INSERT INTO contacts (client_id,contact_id,contact_name,contact_username,contact_email,contact_phone) VALUES (?,?,?,?,?,?)');
    $stmt->bind_param('iissss',$receiver,$clientId,$senderName,$senderName,$sender['email'],$sender['phone']); $stmt->execute(); $stmt->close();
}
// Also create an in-app notification when the Step 13 notification table exists.
try {
    $check=$conn->query("SHOW TABLES LIKE 'cnrbyte_notifications'");
    if ($check && $check->num_rows) {
        $recipientType='client'; $title='New direct message'; $target='/cnrbyte/messages/chat.pge.php'; $note='You received a new message from '.$senderName;
        $stmt=$conn->prepare('INSERT INTO cnrbyte_notifications (recipient_type,recipient_id,notification_type,title,message,target_url) VALUES (?,?,?,?,?,?)');
        $kind='message'; $stmt->bind_param('sissss',$recipientType,$receiver,$kind,$title,$note,$target); $stmt->execute(); $stmt->close();
    }
} catch (Throwable $e) { error_log('CnrByte direct message notification failed: '.$e->getMessage()); }
$_SESSION['contact_info']=['contact_id'=>$receiver,'contact_username'=>$receiverName];
$_SESSION['direct_chat_success']='Message sent.';
header('Location: /cnrbyte/messages/chat.pge.php'); exit;
