<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';
if (!function_exists('cnr_h')) { function cnr_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
function create_branch_form(mysqli $conn): void {
    $token = cnr_csrf_field_local();
    echo '<section class="cnr-form-card"><h3>Create branch</h3><form method="post" action="/cnrbyte/vendors/module_action.run.php">';
    echo $token . '<input type="hidden" name="action" value="create_branch">';
    echo '<label>Branch name<input name="branch_name" maxlength="180" required></label>';
    echo '<label>Branch code<input name="branch_code" maxlength="100" required></label>';
    echo '<label>Phone<input name="phone" type="tel" maxlength="50"></label>';
    echo '<label>Email<input name="email" type="email" maxlength="150"></label>';
    echo '<button class="cnr-button" type="submit">Create branch</button></form></section>';
}
function edit_branch_form(mysqli $conn): void {
    echo '<section class="cnr-form-card"><h3>Manage branches</h3>';
    $vendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
    $stmt = $conn->prepare('SELECT id, branch_name, branch_code, phone, email FROM branches WHERE vendor_id = ? ORDER BY branch_name');
    $stmt->bind_param('i', $vendorId); $stmt->execute(); $res = $stmt->get_result();
    echo '<div class="cnr-table-wrap"><table><thead><tr><th>Branch</th><th>Code</th><th>Phone</th><th>Email</th><th>Update</th></tr></thead><tbody>';
    while ($r = $res->fetch_assoc()) {
        echo '<tr><td>'.cnr_h($r['branch_name']).'</td><td>'.cnr_h($r['branch_code']).'</td><td>'.cnr_h($r['phone']).'</td><td>'.cnr_h($r['email']).'</td><td>';
        echo '<form method="post" action="/cnrbyte/vendors/module_action.run.php" class="cnr-inline-form">'.cnr_csrf_field_local().'<input type="hidden" name="action" value="update_branch"><input type="hidden" name="branch_id" value="'.(int)$r['id'].'">';
        echo '<input name="branch_name" value="'.cnr_h($r['branch_name']).'" required><input name="branch_code" value="'.cnr_h($r['branch_code']).'" required><input name="phone" value="'.cnr_h($r['phone']).'"><input type="email" name="email" value="'.cnr_h($r['email']).'"><button class="cnr-button cnr-button-secondary">Save</button></form></td></tr>';
    }
    echo '</tbody></table></div></section>'; $stmt->close();
}
function create_org_form(mysqli $conn): void { edit_branch_form($conn); }
function cnr_csrf_field_local(): string {
    return '<input type="hidden" name="csrf_token" value="'.cnr_h($_SESSION['csrf_token'] ?? '').'">';
}

function cnr_vendor_lookup_owner_id(mysqli $conn, int $vendorId): int {
    $stmt = $conn->prepare("SELECT COALESCE(client_id, user_id) AS owner_id FROM vendors WHERE id = ? AND status = 'approved' LIMIT 1");
    if (!$stmt) return 0; $stmt->bind_param('i', $vendorId); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); return (int)($row['owner_id'] ?? 0);
}
function cnr_vendor_lookup_options(mysqli $conn, int $vendorId, string $type, string $selected = ''): string {
    $ownerId = cnr_vendor_lookup_owner_id($conn, $vendorId); if ($ownerId < 1) return '';
    $stmt = $conn->prepare('SELECT type_name FROM types WHERE client_id = ? AND type = ? ORDER BY type_name'); if (!$stmt) return '';
    $stmt->bind_param('is', $ownerId, $type); $stmt->execute(); $res = $stmt->get_result(); $html = '';
    while ($row = $res->fetch_assoc()) { $name = (string)$row['type_name']; $e = cnr_h($name); $html .= '<option value="'.$e.'"'.($name === $selected ? ' selected' : '').'>'.$e.'</option>'; }
    $stmt->close(); return $html;
}
