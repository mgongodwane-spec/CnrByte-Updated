<?php
declare(strict_types=1);

/** Output user-owned lookup values safely for settings select controls. */
if (!function_exists('get_types_by_owners_id')) {
    function get_types_by_owners_id(mysqli $conn, string $type): void
    {
        $clientId = (int)($_SESSION['user_info']['id'] ?? 0);
        if (isset($_SESSION['vendor_info']['id'])) {
            $vendorId = (int)$_SESSION['vendor_info']['id'];
            $ownerStmt = $conn->prepare("SELECT COALESCE(client_id, user_id) AS owner_id FROM vendors WHERE id = ? AND status = 'approved' LIMIT 1");
            if ($ownerStmt) { $ownerStmt->bind_param('i', $vendorId); $ownerStmt->execute(); $ownerRow = $ownerStmt->get_result()->fetch_assoc(); $ownerStmt->close(); if ($ownerRow) $clientId = (int)$ownerRow['owner_id']; }
        }
        if ($clientId < 1) return;
        $stmt = $conn->prepare('SELECT type_name FROM types WHERE client_id = ? AND type = ? ORDER BY type_name');
        if (!$stmt) return;
        $stmt->bind_param('is', $clientId, $type);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $value = htmlspecialchars((string)$row['type_name'], ENT_QUOTES, 'UTF-8');
            echo '<option value="' . $value . '">' . $value . '</option>';
        }
        $stmt->close();
    }
}

/** Render owner-scoped lookup options with HTML escaping. */
if (!function_exists('settings_render_lookup_options')) {
    function settings_render_lookup_options(mysqli $conn, string $type, int $ownerId, string $selected = ''): void
    {
        if ($ownerId < 1) return;
        $stmt = $conn->prepare('SELECT type_name FROM types WHERE client_id = ? AND type = ? ORDER BY type_name');
        if (!$stmt) return;
        $stmt->bind_param('is', $ownerId, $type);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $name = (string)$row['type_name'];
            echo '<option value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"' . ($name === $selected ? ' selected' : '') . '>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $stmt->close();
    }
}
