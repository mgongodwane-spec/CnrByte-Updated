<?php
declare(strict_types=1);

function notifications_send(
    mysqli $conn,
    int $senderId,
    int $receiverId,
    string $subject,
    string $message
): void {
    $subject = trim($subject);
    $message = trim($message);

    if ($senderId <= 0 || $receiverId <= 0 || $message === '') {
        return;
    }

    if ($subject === '') {
        $subject = 'Notification';
    }

    $stmt = $conn->prepare(
        "INSERT INTO messages (sender_id, receiver_id, subject, message, is_read)
         VALUES (?, ?, ?, ?, 0)"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('iiss', $senderId, $receiverId, $subject, $message);
    $stmt->execute();
    $stmt->close();
}
