<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/branches/branches.forms.php';
function create_staff_form(mysqli $conn): void {
    $vendorId=(int)($_SESSION['vendor_info']['id'] ?? 0);
    echo '<section class="cnr-form-card"><h3>Add staff member</h3><form method="post" action="/cnrbyte/vendors/module_action.run.php" enctype="multipart/form-data">'.cnr_csrf_field_local().'<input type="hidden" name="action" value="create_staff">';
    echo '<label>Branch<select name="branch_id" required><option value="">Select branch</option>';
    $stmt=$conn->prepare('SELECT id,branch_name FROM branches WHERE vendor_id=? ORDER BY branch_name');$stmt->bind_param('i',$vendorId);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())echo '<option value="'.(int)$r['id'].'">'.cnr_h($r['branch_name']).'</option>';$stmt->close();
    echo '</select></label><label>Assigned shift<select name="shift_id"><option value="">No fixed shift</option>';
    $stmt=$conn->prepare('SELECT id,shift_name FROM shifts WHERE vendor_id=? ORDER BY shift_name');$stmt->bind_param('i',$vendorId);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())echo '<option value="'.(int)$r['id'].'">'.cnr_h($r['shift_name']).'</option>';$stmt->close();
    echo '</select></label><label>Employee code<input name="emp_code" maxlength="100"></label><label>First names<input name="first_names" maxlength="180" required></label><label>Last name<input name="last_name" maxlength="180" required></label><label>Identity number<input name="identity_no" maxlength="100"></label><label>Start date<input name="start_date" type="date"></label><label>Position / rank<input name="rank" maxlength="120"></label><label>Occupation<select name="occupation" required><option value="">Select occupation</option>'.cnr_vendor_lookup_options($conn,$vendorId,'occupation_type').'</select></label><label>Contract type<select name="contract" required><option value="">Select contract type</option>'.cnr_vendor_lookup_options($conn,$vendorId,'contract_type').'</select></label><label>Monthly rate (ZAR)<input name="rate" type="number" min="0" step="0.01" value="0.00"></label><button class="cnr-button">Add staff member</button></form></section>';
}
function edit_staff_qualification_form(mysqli $conn): void {
    $vendorId=(int)($_SESSION['vendor_info']['id'] ?? 0);
    echo '<section class="cnr-form-card"><h3>Add staff qualification</h3><form method="post" action="/cnrbyte/vendors/module_action.run.php" enctype="multipart/form-data">'.cnr_csrf_field_local().'<input type="hidden" name="action" value="create_qualification"><label>Staff member<select name="staff_id" required><option value="">Select staff</option>';
    $stmt=$conn->prepare('SELECT id,first_names,last_name FROM staff WHERE vendor_id=? ORDER BY last_name,first_names');$stmt->bind_param('i',$vendorId);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())echo '<option value="'.(int)$r['id'].'">'.cnr_h($r['first_names'].' '.$r['last_name']).'</option>';$stmt->close();
    echo '</select></label><label>Qualification<select name="qualification" required><option value="">Select qualification</option>'.cnr_vendor_lookup_options($conn,$vendorId,'qualification_type').'</select></label><label>Field<input name="qualification_field" required maxlength="180"></label><label>Supporting document (PDF/JPG/PNG)<input type="file" name="qualification_img" accept=".pdf,.jpg,.jpeg,.png"></label><button class="cnr-button">Save qualification</button></form></section>';
}
function edit_staff_contract_form(mysqli $conn): void {
    $vendorId=(int)($_SESSION['vendor_info']['id'] ?? 0);
    echo '<section class="cnr-form-card"><h3>Update staff contract and rate</h3><form method="post" action="/cnrbyte/vendors/module_action.run.php">'.cnr_csrf_field_local().'<input type="hidden" name="action" value="update_staff_contract"><label>Staff member<select name="staff_id" required><option value="">Select staff</option>';
    $stmt=$conn->prepare('SELECT id,first_names,last_name,contract,rate FROM staff WHERE vendor_id=? ORDER BY last_name,first_names');$stmt->bind_param('i',$vendorId);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())echo '<option value="'.(int)$r['id'].'">'.cnr_h($r['first_names'].' '.$r['last_name'].' — '.$r['contract'].' — R'.$r['rate']).'</option>';$stmt->close();
    echo '</select></label><label>Contract type<select name="contract" required><option value="">Select contract type</option>'.cnr_vendor_lookup_options($conn,$vendorId,'contract_type').'</select></label><label>Monthly rate (ZAR)<input name="rate" type="number" min="0" step="0.01" required></label><button class="cnr-button">Update staff record</button></form></section>';
}
function edit_staff_picture_form(): void {
    echo '<section class="cnr-form-card"><h3>Staff profile documents</h3><p>Profile-picture uploads are disabled until the deployment upload directory and file-serving permissions are verified. Use the qualification form for supported staff documents.</p></section>';
}
