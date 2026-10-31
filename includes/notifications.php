<?php
declare(strict_types=1);

/** Notification helpers. Schema is installed by 08_step13_vendor_org_hr_schema.sql. */
if (!function_exists('cnrbyte_notification_unread_count')) {
    function cnrbyte_notification_unread_count(mysqli $conn, string $recipientType, int $recipientId): int
    {
        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS total FROM cnrbyte_notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0'
        );
        $stmt->bind_param('si', $recipientType, $recipientId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }
}
if (!function_exists('cnrbyte_create_notification')) {
    function cnrbyte_create_notification(
        mysqli $conn, string $recipientType, int $recipientId, string $type,
        string $title, string $message, ?string $targetUrl = null
    ): bool {
        $stmt = $conn->prepare(
            'INSERT INTO cnrbyte_notifications (recipient_type, recipient_id, notification_type, title, message, target_url) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sissss', $recipientType, $recipientId, $type, $title, $message, $targetUrl);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
