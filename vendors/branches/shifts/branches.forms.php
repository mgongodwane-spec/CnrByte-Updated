<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/branches/branches.forms.php';
function create_shift_form(mysqli $conn): void {
    $vendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
    echo '<section class="cnr-form-card"><h3>Create shift</h3><form method="post" action="/cnrbyte/vendors/module_action.run.php">'.cnr_csrf_field_local().'<input type="hidden" name="action" value="create_shift">';
    echo '<label>Branch<select name="branch_id" required><option value="">Select branch</option>';
    $stmt=$conn->prepare('SELECT id, branch_name FROM branches WHERE vendor_id = ? ORDER BY branch_name'); $stmt->bind_param('i',$vendorId); $stmt->execute();
    $res=$stmt->get_result(); while($r=$res->fetch_assoc()) echo '<option value="'.(int)$r['id'].'">'.cnr_h($r['branch_name']).'</option>'; $stmt->close();
    echo '</select></label><label>Shift name<input name="shift_name" maxlength="150" required placeholder="e.g. Morning shift"></label><button class="cnr-button">Create shift</button></form></section>';
}
function edit_shift_form(mysqli $conn): void {
    $vendorId=(int)($_SESSION['vendor_info']['id'] ?? 0);
    $stmt=$conn->prepare('SELECT s.id,s.shift_name,s.branch_id,b.branch_name FROM shifts s JOIN branches b ON b.id=s.branch_id WHERE s.vendor_id=? ORDER BY b.branch_name,s.shift_name');
    $stmt->bind_param('i',$vendorId);$stmt->execute();$res=$stmt->get_result();
    echo '<section class="cnr-form-card"><h3>Manage shifts</h3><div class="cnr-table-wrap"><table><thead><tr><th>Branch</th><th>Shift</th><th>Update</th></tr></thead><tbody>';
    while($r=$res->fetch_assoc()){echo '<tr><td>'.cnr_h($r['branch_name']).'</td><td>'.cnr_h($r['shift_name']).'</td><td><form method="post" action="/cnrbyte/vendors/module_action.run.php" class="cnr-inline-form">'.cnr_csrf_field_local().'<input type="hidden" name="action" value="update_shift"><input type="hidden" name="shift_id" value="'.(int)$r['id'].'"><input name="shift_name" value="'.cnr_h($r['shift_name']).'" required><button class="cnr-button cnr-button-secondary">Save</button></form></td></tr>';}
    echo '</tbody></table></div></section>'; $stmt->close();
}
function create_org_form(mysqli $conn): void { edit_shift_form($conn); }
