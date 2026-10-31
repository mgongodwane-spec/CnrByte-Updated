<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST' || empty($_SESSION['user_info']['id'])) { http_response_code(403); exit('Not authorised.'); }
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token']??null)) { http_response_code(403); exit('Invalid or expired form token.'); }
$clientId=(int)$_SESSION['user_info']['id']; $vendorId=(int)($_SESSION['vendor_info']['id']??0);
if($vendorId<1){http_response_code(403);exit('Select a vendor workspace first.');}
$stmt=$conn->prepare('SELECT id FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1');$stmt->bind_param('iii',$vendorId,$clientId,$clientId);$stmt->execute();$owned=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$owned){unset($_SESSION['vendor_info']);http_response_code(403);exit('You do not have access to this vendor workspace.');}
$fields=['reg_name'=>180,'reg_no'=>120,'tax_no'=>120,'email'=>150,'phone'=>50,'market'=>120,'business_type'=>120,'business_form'=>120,'operation_status'=>120,'str_address'=>255,'phy_suburb'=>150,'phy_town'=>150,'phy_province'=>150,'phy_postal_code'=>50,'po_box'=>100,'pos_suburb'=>150,'pos_town'=>150,'pos_province'=>150,'pos_postal_code'=>50,'about_para_text'=>5000,'vision_para_text'=>3000,'mission_para_text'=>3000,'values_para_text'=>3000,'client_care'=>255,'website'=>255];
$data=[]; foreach($fields as $key=>$max){$data[$key]=trim((string)($_POST[$key]??''));if(mb_strlen($data[$key])>$max){$_SESSION['vendor_profile_error']='One or more fields exceed the allowed length.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}}
if($data['reg_name']===''){$_SESSION['vendor_profile_error']='Business name is required.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}
if($data['email']!==''&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL)){$_SESSION['vendor_profile_error']='Enter a valid business email address.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}
if($data['website']!=='' && !filter_var($data['website'],FILTER_VALIDATE_URL)){$_SESSION['vendor_profile_error']='Enter a complete website URL, including https://.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}
$dateFields=['reg_date','starting_date','fin_year_end']; foreach($dateFields as $k){$data[$k]=trim((string)($_POST[$k]??''));if($data[$k]!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$data[$k])){$_SESSION['vendor_profile_error']='Enter valid dates.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;} if($data[$k]==='')$data[$k]=null;}
$uploadedImages=[];$pendingUploads=[];
$uploadDir=__DIR__.'/../uploads/vendors/profile';
foreach(['logo_img','about_img','vision_img','mission_img','values_img'] as $imageField){
 $file=$_FILES[$imageField]??null; if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || (int)($file['size']??0)>5000000 || !is_uploaded_file((string)($file['tmp_name']??''))){$_SESSION['vendor_profile_error']='An image upload failed or exceeds 5 MB.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}
 $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);$allowedMime=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
 if(!isset($allowedMime[$ext]) || $mime!==$allowedMime[$ext]){$_SESSION['vendor_profile_error']='Use valid JPG, PNG or WebP images only.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}
 $pendingUploads[$imageField]=['tmp_name'=>(string)$file['tmp_name'],'extension'=>$ext];
}
if($pendingUploads){if(!is_dir($uploadDir)&&!mkdir($uploadDir,0750,true)&&!is_dir($uploadDir)){$_SESSION['vendor_profile_error']='The image upload directory is unavailable.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}
 foreach($pendingUploads as $field=>$upload){$filename=bin2hex(random_bytes(16)).'.'.$upload['extension'];if(!move_uploaded_file($upload['tmp_name'],$uploadDir.'/'.$filename)){foreach($uploadedImages as $old){$oldPath=$uploadDir.'/'.$old;if(is_file($oldPath))@unlink($oldPath);}$_SESSION['vendor_profile_error']='An image could not be saved.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}$uploadedImages[$field]=$filename;}
}
try{
 $stmt=$conn->prepare('SELECT id FROM vendors WHERE id<>? AND ((?<>\'\' AND reg_no=?) OR (?<>\'\' AND LOWER(TRIM(email))=LOWER(TRIM(?)))) LIMIT 1');
 $stmt->bind_param('issss',$vendorId,$data['reg_no'],$data['reg_no'],$data['email'],$data['email']);$stmt->execute();$duplicate=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();
 if($duplicate){foreach(($uploadedImages??[]) as $uploadedName){$uploadedPath=$uploadDir.'/'.$uploadedName;if(is_file($uploadedPath))@unlink($uploadedPath);}$_SESSION['vendor_profile_error']='That registration number or business email is already in use.';header('Location: /cnrbyte/vendors/profile.pge.php');exit;}
 $conn->begin_transaction();
 $stmt=$conn->prepare('UPDATE vendors SET reg_name=?,reg_no=?,tax_no=?,email=?,phone=?,market=?,reg_date=?,starting_date=?,business_type=?,business_form=?,operation_status=?,fin_year_end=? WHERE id=? AND (client_id=? OR user_id=?)');
 $stmt->bind_param('ssssssssssssiii',$data['reg_name'],$data['reg_no'],$data['tax_no'],$data['email'],$data['phone'],$data['market'],$data['reg_date'],$data['starting_date'],$data['business_type'],$data['business_form'],$data['operation_status'],$data['fin_year_end'],$vendorId,$clientId,$clientId);$stmt->execute();$stmt->close();
 $stmt=$conn->prepare('SELECT id FROM profiles WHERE vendor_id=? ORDER BY id DESC LIMIT 1');$stmt->bind_param('i',$vendorId);$stmt->execute();$existing=$stmt->get_result()->fetch_assoc();$stmt->close();
 if($existing){$profileId=(int)$existing['id'];$stmt=$conn->prepare('UPDATE profiles SET str_address=?,phy_suburb=?,phy_town=?,phy_province=?,phy_postal_code=?,po_box=?,pos_suburb=?,pos_town=?,pos_province=?,pos_postal_code=?,about_para_text=?,vision_para_text=?,mission_para_text=?,values_para_text=? WHERE id=? AND vendor_id=?');$stmt->bind_param('ssssssssssssssii',$data['str_address'],$data['phy_suburb'],$data['phy_town'],$data['phy_province'],$data['phy_postal_code'],$data['po_box'],$data['pos_suburb'],$data['pos_town'],$data['pos_province'],$data['pos_postal_code'],$data['about_para_text'],$data['vision_para_text'],$data['mission_para_text'],$data['values_para_text'],$profileId,$vendorId);}
 else{$stmt=$conn->prepare('INSERT INTO profiles (vendor_id,str_address,phy_suburb,phy_town,phy_province,phy_postal_code,po_box,pos_suburb,pos_town,pos_province,pos_postal_code,about_para_text,vision_para_text,mission_para_text,values_para_text) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->bind_param('issssssssssssss',$vendorId,$data['str_address'],$data['phy_suburb'],$data['phy_town'],$data['phy_province'],$data['phy_postal_code'],$data['po_box'],$data['pos_suburb'],$data['pos_town'],$data['pos_province'],$data['pos_postal_code'],$data['about_para_text'],$data['vision_para_text'],$data['mission_para_text'],$data['values_para_text']);}
 $stmt->execute();$stmt->close();
 $profileId=(int)($existing['id']??$conn->insert_id);
 foreach($uploadedImages as $imageColumn=>$imageFilename){$stmt=$conn->prepare("UPDATE profiles SET {$imageColumn}=? WHERE id=? AND vendor_id=?");$stmt->bind_param('sii',$imageFilename,$profileId,$vendorId);$stmt->execute();$stmt->close();}
 foreach(['Client Care'=>$data['client_care'],'Website'=>$data['website']] as $name=>$address){$stmt=$conn->prepare('SELECT id FROM contacts WHERE vendor_id=? AND contact_name=? ORDER BY id DESC LIMIT 1');$stmt->bind_param('is',$vendorId,$name);$stmt->execute();$contact=$stmt->get_result()->fetch_assoc();$stmt->close();if($contact){$id=(int)$contact['id'];$stmt=$conn->prepare('UPDATE contacts SET contact_address=? WHERE id=? AND vendor_id=?');$stmt->bind_param('sii',$address,$id,$vendorId);}else{$stmt=$conn->prepare('INSERT INTO contacts (vendor_id,owners_id,contact_name,contact_address) VALUES (?,?,?,?)');$stmt->bind_param('iiss',$vendorId,$vendorId,$name,$address);} $stmt->execute();$stmt->close();}
 $conn->commit(); $_SESSION['vendor_info']['reg_name']=$data['reg_name']; $_SESSION['vendor_profile_success']='Business profile, addresses and customer contact channels have been saved.';
}catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignored){} foreach(($uploadedImages??[]) as $uploadedName){$uploadedPath=$uploadDir.'/'.$uploadedName;if(is_file($uploadedPath))@unlink($uploadedPath);} error_log('CnrByte vendor profile update failed: '.$e->getMessage());$_SESSION['vendor_profile_error']='The business profile could not be saved. Confirm that the Step 16 schema migration has been applied.';}
header('Location: /cnrbyte/vendors/profile.pge.php');exit;
