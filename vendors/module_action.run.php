<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/select.run.php';

function cnr_safe_return_target(string $fallback = '/cnrbyte/vendors/dashboard.pge.php'): string {
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($referer !== '' && $host !== '' && parse_url($referer, PHP_URL_HOST) === preg_replace('/:\\d+$/', '', $host)) {
        $path = (string)(parse_url($referer, PHP_URL_PATH) ?? '');
        if (substr($path, 0, 8) === '/cnrbyte/') {
            $query = (string)(parse_url($referer, PHP_URL_QUERY) ?? '');
            return $path . ($query !== '' ? '?' . $query : '');
        }
    }
    return $fallback;
}
function cnr_action_fail(string $message, string $back = ''): void {
    $_SESSION['errors_create'] = ['action' => $message];
    header('Location: ' . ($back !== '' ? $back : cnr_safe_return_target()));
    exit;
}
function cnr_action_success(string $message, string $back = ''): void {
    $_SESSION['success_create'] = ['action' => $message];
    header('Location: ' . ($back !== '' ? $back : cnr_safe_return_target()));
    exit;
}
function cnr_require_vendor(mysqli $conn): int {
    $vendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
    $clientId = (int)($_SESSION['user_info']['id'] ?? 0);
    if ($vendorId < 1 || $clientId < 1) cnr_action_fail('Please sign in and select a vendor workspace.');
    $stmt = $conn->prepare('SELECT id FROM vendors WHERE id = ? AND (client_id = ? OR user_id = ?) LIMIT 1');
    $stmt->bind_param('iii', $vendorId, $clientId, $clientId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ok) cnr_action_fail('You do not have access to this vendor workspace.');
    return $vendorId;
}
function cnr_vendor_owner_id(mysqli $conn, int $vendorId): int {
    $stmt=$conn->prepare("SELECT COALESCE(client_id, user_id) AS owner_id FROM vendors WHERE id=? AND status='approved' LIMIT 1");
    if(!$stmt)return 0; $stmt->bind_param('i',$vendorId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close(); return (int)($row['owner_id']??0);
}
function cnr_vendor_lookup_exists(mysqli $conn, int $vendorId, string $type, string $value): bool {
    $ownerId=cnr_vendor_owner_id($conn,$vendorId); if($ownerId<1||$value==='')return false;
    $stmt=$conn->prepare('SELECT id FROM types WHERE client_id=? AND type=? AND type_name=? LIMIT 1'); if(!$stmt)return false;
    $stmt->bind_param('iss',$ownerId,$type,$value); $stmt->execute(); $ok=(bool)$stmt->get_result()->fetch_assoc(); $stmt->close(); return $ok;
}
function cnr_owned_id(mysqli $conn, string $table, int $id, int $vendorId): bool {
    $allowed = ['branches', 'shifts', 'staff'];
    if (!in_array($table, $allowed, true)) return false;
    $sql = "SELECT id FROM {$table} WHERE id = ? AND vendor_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql); $stmt->bind_param('ii', $id, $vendorId);
    $stmt->execute(); $found = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
    return $found;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
if (!cnrbyte_csrf_validate()) { http_response_code(403); exit('Invalid or expired form token. Refresh the page and try again.'); }
$vendorId = cnr_require_vendor($conn);
$actionName = (string)($_POST['action'] ?? '');
$back = '';
try {
    switch ($actionName) {
        case 'create_branch': {
            $name=trim((string)($_POST['branch_name']??'')); $code=trim((string)($_POST['branch_code']??''));
            $phone=trim((string)($_POST['phone']??'')); $email=trim((string)($_POST['email']??''));
            if ($name==='' || $code==='' || mb_strlen($name)>180 || mb_strlen($code)>120 || mb_strlen($phone)>50 || mb_strlen($email)>150) cnr_action_fail('Enter valid branch details within the allowed field lengths.');
            if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) cnr_action_fail('Enter a valid email address.');
            $stmt=$conn->prepare('SELECT id FROM branches WHERE vendor_id=? AND branch_code=? LIMIT 1');$stmt->bind_param('is',$vendorId,$code);$stmt->execute();$exists=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();
            if($exists) cnr_action_fail('That branch code is already used by this vendor.');
            $stmt=$conn->prepare('INSERT INTO branches (vendor_id,branch_name,branch_code,phone,email) VALUES (?,?,?,?,?)');$stmt->bind_param('issss',$vendorId,$name,$code,$phone,$email);$stmt->execute();$stmt->close();
            cnr_action_success('Branch created.');
        }
        case 'update_branch': {
            $id=(int)($_POST['branch_id']??0);$name=trim((string)($_POST['branch_name']??''));$code=trim((string)($_POST['branch_code']??''));
            $phone=trim((string)($_POST['phone']??''));$email=trim((string)($_POST['email']??''));
            if(!cnr_owned_id($conn,'branches',$id,$vendorId)) cnr_action_fail('Branch not found in this vendor workspace.');
            if($name===''||$code===''||mb_strlen($name)>180||mb_strlen($code)>120||mb_strlen($phone)>50||mb_strlen($email)>150)cnr_action_fail('Enter valid branch details within the allowed field lengths.');
            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))cnr_action_fail('Enter a valid email address.');
            $dup=$conn->prepare('SELECT id FROM branches WHERE vendor_id=? AND branch_code=? AND id<>? LIMIT 1');$dup->bind_param('isi',$vendorId,$code,$id);$dup->execute();$duplicate=(bool)$dup->get_result()->fetch_assoc();$dup->close();if($duplicate)cnr_action_fail('That branch code is already used by this vendor.');
            $stmt=$conn->prepare('UPDATE branches SET branch_name=?,branch_code=?,phone=?,email=? WHERE id=? AND vendor_id=?');$stmt->bind_param('ssssii',$name,$code,$phone,$email,$id,$vendorId);$stmt->execute();$stmt->close();
            cnr_action_success('Branch updated.');
        }
        case 'create_shift': {
            $branchId=(int)($_POST['branch_id']??0);$name=trim((string)($_POST['shift_name']??''));
            if(!cnr_owned_id($conn,'branches',$branchId,$vendorId))cnr_action_fail('Select a branch belonging to this vendor.');
            if($name===''||mb_strlen($name)>150)cnr_action_fail('Shift name is required and must be 150 characters or fewer.');
            $stmt=$conn->prepare('SELECT id FROM shifts WHERE vendor_id=? AND branch_id=? AND shift_name=? LIMIT 1');$stmt->bind_param('iis',$vendorId,$branchId,$name);$stmt->execute();$exists=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();
            if($exists)cnr_action_fail('That shift already exists for this branch.');
            $stmt=$conn->prepare('INSERT INTO shifts (vendor_id,branch_id,shift_name) VALUES (?,?,?)');$stmt->bind_param('iis',$vendorId,$branchId,$name);$stmt->execute();$stmt->close();
            cnr_action_success('Shift created.');
        }
        case 'update_shift': {
            $id=(int)($_POST['shift_id']??0);$name=trim((string)($_POST['shift_name']??''));
            if(!cnr_owned_id($conn,'shifts',$id,$vendorId))cnr_action_fail('Shift not found in this vendor workspace.');
            if($name===''||mb_strlen($name)>150)cnr_action_fail('Shift name is required and must be 150 characters or fewer.');
            $current=$conn->prepare('SELECT branch_id FROM shifts WHERE id=? AND vendor_id=? LIMIT 1');$current->bind_param('ii',$id,$vendorId);$current->execute();$currentBranch=(int)($current->get_result()->fetch_assoc()['branch_id']??0);$current->close();
            $dup=$conn->prepare('SELECT id FROM shifts WHERE vendor_id=? AND branch_id=? AND shift_name=? AND id<>? LIMIT 1');$dup->bind_param('iisi',$vendorId,$currentBranch,$name,$id);$dup->execute();$duplicate=(bool)$dup->get_result()->fetch_assoc();$dup->close();if($duplicate)cnr_action_fail('That shift name is already used for this branch.');
            $stmt=$conn->prepare('UPDATE shifts SET shift_name=? WHERE id=? AND vendor_id=?');$stmt->bind_param('sii',$name,$id,$vendorId);$stmt->execute();$stmt->close();
            cnr_action_success('Shift updated.');
        }
        case 'create_staff': {
            $branchId=(int)($_POST['branch_id']??0);$shiftRaw=(string)($_POST['shift_id']??'');$shiftId=$shiftRaw===''?null:(int)$shiftRaw;
            $first=trim((string)($_POST['first_names']??''));$last=trim((string)($_POST['last_name']??''));
            $emp=trim((string)($_POST['emp_code']??''));$identity=trim((string)($_POST['identity_no']??''));
            $start=trim((string)($_POST['start_date']??''));$rank=trim((string)($_POST['rank']??''));$occupation=trim((string)($_POST['occupation']??''));
            $contract=trim((string)($_POST['contract']??''));$rate=(float)($_POST['rate']??0);
            if(!cnr_vendor_lookup_exists($conn,$vendorId,'occupation_type',$occupation)||!cnr_vendor_lookup_exists($conn,$vendorId,'contract_type',$contract))cnr_action_fail('Select an occupation and contract type configured in Settings.');
            if(!cnr_owned_id($conn,'branches',$branchId,$vendorId))cnr_action_fail('Select a branch belonging to this vendor.');
            if($shiftId!==null&&!cnr_owned_id($conn,'shifts',$shiftId,$vendorId))cnr_action_fail('Select a shift belonging to this vendor.');
            if ($shiftId !== null) {
                $check = $conn->prepare('SELECT id FROM shifts WHERE id=? AND vendor_id=? AND branch_id=? LIMIT 1');
                $check->bind_param('iii', $shiftId, $vendorId, $branchId); $check->execute();
                $matches = (bool)$check->get_result()->fetch_assoc(); $check->close();
                if (!$matches) cnr_action_fail('The selected shift must belong to the selected branch.');
            }
            if($first===''||$last===''||mb_strlen($first)>180||mb_strlen($last)>180||mb_strlen($emp)>100||mb_strlen($identity)>100||mb_strlen($rank)>120||mb_strlen($occupation)>150||mb_strlen($contract)>120)cnr_action_fail('Enter valid staff details within the allowed field lengths.');
            if(!is_numeric((string)($_POST['rate']??''))||!is_finite($rate)||$rate<0||$rate>9999999999.99)cnr_action_fail('Enter a valid non-negative staff rate.');
            if($start!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$start))cnr_action_fail('Enter a valid start date.');
            if($start!=='' && (DateTime::createFromFormat('!Y-m-d',$start)===false || DateTime::createFromFormat('!Y-m-d',$start)->format('Y-m-d')!==$start))cnr_action_fail('Enter a valid start date.');
            if($emp!==''){$stmt=$conn->prepare('SELECT id FROM staff WHERE vendor_id=? AND emp_code=? LIMIT 1');$stmt->bind_param('is',$vendorId,$emp);$stmt->execute();$exists=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();if($exists)cnr_action_fail('Employee code already exists for this vendor.');}
            $startVal=$start===''?null:$start;
            $stmt=$conn->prepare('INSERT INTO staff (vendor_id,branch_id,shift_id,identity_no,first_names,last_name,emp_code,start_date,rank,occupation,rate,contract) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $types = 'iii' . str_repeat('s', 7) . 'd' . 's';
            $stmt->bind_param($types,$vendorId,$branchId,$shiftId,$identity,$first,$last,$emp,$startVal,$rank,$occupation,$rate,$contract);
            $stmt->execute();$stmt->close();
            cnr_action_success('Staff member added.');
        }
        case 'update_staff_contract': {
            $id=(int)($_POST['staff_id']??0);$contract=trim((string)($_POST['contract']??''));$rate=(float)($_POST['rate']??-1);
            if(!cnr_owned_id($conn,'staff',$id,$vendorId))cnr_action_fail('Staff member not found in this vendor workspace.');
            if(!cnr_vendor_lookup_exists($conn,$vendorId,'contract_type',$contract)||!is_numeric((string)($_POST['rate']??''))||!is_finite($rate)||$rate<0||$rate>9999999999.99)cnr_action_fail('Select a configured contract type and enter a valid non-negative rate.');
            $stmt=$conn->prepare('UPDATE staff SET contract=?,rate=? WHERE id=? AND vendor_id=?');$stmt->bind_param('sdii',$contract,$rate,$id,$vendorId);$stmt->execute();$stmt->close();
            cnr_action_success('Staff contract updated.');
        }
        case 'create_qualification': {
            $staffId=(int)($_POST['staff_id']??0);$qualification=trim((string)($_POST['qualification']??''));$field=trim((string)($_POST['qualification_field']??''));
            if(!cnr_owned_id($conn,'staff',$staffId,$vendorId))cnr_action_fail('Staff member not found in this vendor workspace.');
            if(!cnr_vendor_lookup_exists($conn,$vendorId,'qualification_type',$qualification)||$field==='')cnr_action_fail('Select a qualification configured in Settings and enter its field.');
            $filename=null;
            if(isset($_FILES['qualification_img'])&&$_FILES['qualification_img']['error']!==UPLOAD_ERR_NO_FILE){
                $file=$_FILES['qualification_img']; if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||(int)($file['size']??0)<=0||(int)$file['size']>5000000||!is_uploaded_file((string)($file['tmp_name']??'')))cnr_action_fail('Document upload failed or exceeds 5 MB.');
                $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));$mimeMap=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];if(!isset($mimeMap[$ext]))cnr_action_fail('Only PDF, JPG, JPEG, and PNG files are accepted.');
                $detected=(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);if($detected!==$mimeMap[$ext])cnr_action_fail('The document content does not match its file extension.');
                $dir=dirname(__DIR__).'/uploads/staff/qualifications';if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))cnr_action_fail('Upload directory could not be created.');
                $filename=bin2hex(random_bytes(16)).'.'.$ext;if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$filename))cnr_action_fail('Could not save the document.');
            }
            $stmt=$conn->prepare('INSERT INTO qualifications (staff_id,qualification,qualification_field,qualification_img) VALUES (?,?,?,?)');$stmt->bind_param('isss',$staffId,$qualification,$field,$filename);$stmt->execute();$stmt->close();
            cnr_action_success('Qualification saved.');
        }
        case 'create_roster': {
            $branchId=(int)($_POST['branch_id']??0);$shiftId=(int)($_POST['shift_id']??0);$staffId=(int)($_POST['staff_id']??0);
            $forStaff=(int)($_POST['for_staff']??0)===1;$date=(string)($_POST['day_date']??'');$dayName=(string)($_POST['day_name']??'');$dayType=(string)($_POST['day_type']??'normal');$timeOfDay=(string)($_POST['time_of_day']??'day');$status=(string)($_POST['shift_status']??'scheduled');
            $timeIn=(string)($_POST['time_in']??'');$timeOut=(string)($_POST['time_out']??'');$lunchIn=(string)($_POST['lunch_in']??'');$lunchOut=(string)($_POST['lunch_out']??'');
            if(!cnr_owned_id($conn,'branches',$branchId,$vendorId)||!cnr_owned_id($conn,'shifts',$shiftId,$vendorId))cnr_action_fail('Branch and shift must belong to this vendor.');
            $branchCheck=$conn->prepare('SELECT id FROM shifts WHERE id=? AND vendor_id=? AND branch_id=? LIMIT 1');
            $branchCheck->bind_param('iii',$shiftId,$vendorId,$branchId);$branchCheck->execute();$shiftMatches=(bool)$branchCheck->get_result()->fetch_assoc();$branchCheck->close();
            if(!$shiftMatches)cnr_action_fail('The selected shift does not belong to the selected branch.');
            if($forStaff && !cnr_owned_id($conn,'staff',$staffId,$vendorId))cnr_action_fail('Select a staff member belonging to this vendor.');
            $validDayNames=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!($dateObj=DateTime::createFromFormat('!Y-m-d',$date))||$dateObj->format('Y-m-d')!==$date||!in_array($dayName,$validDayNames,true)||!in_array($dayType,['normal','public_holiday','overtime'],true)||!in_array($timeOfDay,['day','night'],true)||!in_array($status,['scheduled','confirmed','leave'],true)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$timeIn)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$timeOut)||(($lunchIn==='')!==($lunchOut===''))||($lunchIn!==''&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$lunchIn))||($lunchOut!==''&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$lunchOut)))cnr_action_fail('Enter a valid date, day type, status, and time range.');
            $shiftName='';$stmt=$conn->prepare('SELECT shift_name FROM shifts WHERE id=? AND vendor_id=?');$stmt->bind_param('ii',$shiftId,$vendorId);$stmt->execute();$shiftName=(string)($stmt->get_result()->fetch_assoc()['shift_name']??'');$stmt->close();
            $hours=(strtotime($date.' '.$timeOut)<strtotime($date.' '.$timeIn)?86400:0)+strtotime($date.' '.$timeOut)-strtotime($date.' '.$timeIn);
            $hours=max(0,$hours)/3600;
            if($lunchIn!==''&&$lunchOut!==''){$lunch=(strtotime($date.' '.$lunchOut)<strtotime($date.' '.$lunchIn)?86400:0)+strtotime($date.' '.$lunchOut)-strtotime($date.' '.$lunchIn);$hours=max(0,$hours-max(0,$lunch)/3600);}
            $hours=round($hours,2);
            $rate=0.0;$rateDescription='Unspecified';
            $stmt=$conn->prepare('SELECT rate,rate_description FROM hr_rates WHERE vendor_id=? AND day_name=? AND day_type=? AND time_of_day=? ORDER BY id DESC LIMIT 1');$stmt->bind_param('isss',$vendorId,$dayName,$dayType,$timeOfDay);$stmt->execute();$rateRow=$stmt->get_result()->fetch_assoc();$stmt->close();
            if($rateRow){$rate=(float)$rateRow['rate'];$rateDescription=(string)$rateRow['rate_description'];}
            $gross=round($rate*$hours,2);$staffValue=$forStaff?$staffId:0;
            $duplicate=$conn->prepare('SELECT id FROM roster WHERE vendor_id=? AND branch_id=? AND shift_id=? AND staff_id=? AND day_date=? AND time_of_day=? LIMIT 1');$duplicate->bind_param('iiiiss',$vendorId,$branchId,$shiftId,$staffValue,$date,$timeOfDay);$duplicate->execute();$alreadyExists=(bool)$duplicate->get_result()->fetch_assoc();$duplicate->close();if($alreadyExists)cnr_action_fail('A roster entry already exists for this shift, staff member, date and time category.');
            $stmt=$conn->prepare('INSERT INTO roster (vendor_id,branch_id,shift_id,staff_id,shift_name,day_date,day_name,day_type,time_of_day,shift_status,time_in,time_out,lunch_in,lunch_out,total_hours,rate,rate_description,gross_earnings) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $types = 'iiii' . str_repeat('s', 10) . 'd' . 'd' . 's' . 'd';
            $stmt->bind_param($types,$vendorId,$branchId,$shiftId,$staffValue,$shiftName,$date,$dayName,$dayType,$timeOfDay,$status,$timeIn,$timeOut,$lunchIn,$lunchOut,$hours,$rate,$rateDescription,$gross);
            $stmt->execute();$stmt->close();
            cnr_action_success('Roster entry saved.');
        }
        case 'create_leave': {
            $staffId=(int)($_POST['staff_id']??0);$type=trim((string)($_POST['leave_type']??''));$start=(string)($_POST['start_date']??'');$end=(string)($_POST['end_date']??'');$reason=trim((string)($_POST['reason']??''));
            if(!cnr_owned_id($conn,'staff',$staffId,$vendorId))cnr_action_fail('Staff member not found in this vendor workspace.');
            $ownerId=cnr_vendor_owner_id($conn,$vendorId); $validLeaveTypes=[]; $lookup=$conn->prepare('SELECT type_name FROM types WHERE client_id=? AND type=\'leave_type\''); $lookup->bind_param('i',$ownerId); $lookup->execute(); $lookupRes=$lookup->get_result(); while($lookupRow=$lookupRes->fetch_assoc())$validLeaveTypes[]=(string)$lookupRow['type_name']; $lookup->close();
            $startDate=DateTime::createFromFormat('!Y-m-d',$start);$endDate=DateTime::createFromFormat('!Y-m-d',$end);
            if(!in_array($type,$validLeaveTypes,true)||!$startDate||!$endDate||$startDate->format('Y-m-d')!==$start||$endDate->format('Y-m-d')!==$end||$end<$start||mb_strlen($reason)>500)cnr_action_fail('Enter a valid leave type, date range and reason (maximum 500 characters).');
            $branchId=0;$stmt=$conn->prepare('SELECT branch_id FROM staff WHERE id=? AND vendor_id=?');$stmt->bind_param('ii',$staffId,$vendorId);$stmt->execute();$branchId=(int)($stmt->get_result()->fetch_assoc()['branch_id']??0);$stmt->close();
            $days=(strtotime($end)-strtotime($start))/86400+1;
            $stmt=$conn->prepare("INSERT INTO staff_leave (vendor_id,branch_id,staff_id,leave_type,start_date,end_date,days,reason) VALUES (?,?,?,?,?,?,?,?)");$stmt->bind_param('iiisssds',$vendorId,$branchId,$staffId,$type,$start,$end,$days,$reason);$stmt->execute();$stmt->close();
            cnr_action_success('Leave request recorded as pending approval.');
        }
        case 'create_hr_rate': {
            $dayName=(string)($_POST['day_name']??'');$dayType=(string)($_POST['day_type']??'');$time=(string)($_POST['time_of_day']??'');$description=trim((string)($_POST['rate_description']??''));$rate=(float)($_POST['rate']??-1);
            if(!in_array($dayName,['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],true)||!in_array($dayType,['normal','public_holiday','overtime'],true)||!in_array($time,['day','night'],true)||$description===''||mb_strlen($description)>120||!is_numeric((string)($_POST['rate']??''))||!is_finite($rate)||$rate<0||$rate>9999999999.99)cnr_action_fail('Complete all rate fields with a valid non-negative rate.');
            $clientId=(int)($_SESSION['user_info']['id']??0);
            $dup=$conn->prepare('SELECT id FROM hr_rates WHERE vendor_id=? AND day_name=? AND day_type=? AND time_of_day=? LIMIT 1');$dup->bind_param('isss',$vendorId,$dayName,$dayType,$time);$dup->execute();$exists=(bool)$dup->get_result()->fetch_assoc();$dup->close();if($exists)cnr_action_fail('A rate is already configured for that day, day type and time category.');
            $stmt=$conn->prepare('INSERT INTO hr_rates (client_id,vendor_id,day_name,time_of_day,day_type,rate_description,rate) VALUES (?,?,?,?,?,?,?)');$stmt->bind_param('iissssd',$clientId,$vendorId,$dayName,$time,$dayType,$description,$rate);$stmt->execute();$stmt->close();
            cnr_action_success('Hourly rate saved.');
        }
        case 'record_payroll_item': {
            $staffId=(int)($_POST['staff_id']??0);$entryType=(string)($_POST['entry_type']??'');$description=trim((string)($_POST['description']??''));$amount=(float)($_POST['amount']??0);$period=(string)($_POST['payroll_period']??'');
            if(!cnr_owned_id($conn,'staff',$staffId,$vendorId))cnr_action_fail('Staff member not found in this vendor workspace.');
            $lookupType=$entryType==='earning'?'earnings_type':'deductions_type';
            if(!in_array($entryType,['earning','deduction'],true)||!cnr_vendor_lookup_exists($conn,$vendorId,$lookupType,$description)||mb_strlen($description)>180||!is_numeric((string)($_POST['amount']??''))||!is_finite($amount)||$amount<=0||$amount>9999999999.99||!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$period))cnr_action_fail('Enter a valid payroll item.');
            $branchId=0;$stmt=$conn->prepare('SELECT branch_id FROM staff WHERE id=? AND vendor_id=?');$stmt->bind_param('ii',$staffId,$vendorId);$stmt->execute();$branchId=(int)($stmt->get_result()->fetch_assoc()['branch_id']??0);$stmt->close();
            $createdBy=(int)($_SESSION['user_info']['id']??0);$stmt=$conn->prepare("INSERT INTO staff_earnings_deductions (vendor_id,branch_id,staff_id,entry_type,description,amount,payroll_period,status,created_by) VALUES (?,?,?,?,?,?,?,'draft',?)");$stmt->bind_param('iiissdsi',$vendorId,$branchId,$staffId,$entryType,$description,$amount,$period,$createdBy);$stmt->execute();$stmt->close();
            cnr_action_success('Payroll item saved as draft; it has not been approved or paid.');
        }
        default: cnr_action_fail('Unknown action.');
    }
} catch (Throwable $e) {
    error_log('CnrByte module action error: '.$e->getMessage());
    cnr_action_fail('The change could not be saved. Check the required database migration and try again.');
}
