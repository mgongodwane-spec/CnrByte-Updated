<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/conn.dbh.php';
if (empty($_SESSION['user_info']['id'])) { header('Location: /cnrbyte/signup_login/signup_login.pge.php'); exit; }
$clientId=(int)$_SESSION['user_info']['id'];
$vendorId=(int)($_SESSION['vendor_info']['id']??0);
if ($vendorId<1) { header('Location: /cnrbyte/vendors/select.pge.php'); exit; }
$stmt=$conn->prepare('SELECT * FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1');
$stmt->bind_param('iii',$vendorId,$clientId,$clientId); $stmt->execute(); $vendor=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$vendor) { unset($_SESSION['vendor_info']); http_response_code(403); exit('You do not have access to this vendor workspace.'); }
$_SESSION['vendor_info']['reg_name']=(string)$vendor['reg_name'];
$_SESSION['current_dashboard']='vendors_dashboard'; $_SESSION['selected_pge']='profile_pge';
$stmt=$conn->prepare('SELECT * FROM profiles WHERE vendor_id=? ORDER BY id DESC LIMIT 1'); $stmt->bind_param('i',$vendorId); $stmt->execute(); $profile=$stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
$contacts=[]; $stmt=$conn->prepare("SELECT contact_name,contact_address FROM contacts WHERE vendor_id=? AND contact_name IN ('Client Care','Website') ORDER BY id DESC"); $stmt->bind_param('i',$vendorId); $stmt->execute(); $res=$stmt->get_result(); while($r=$res->fetch_assoc()) if(!isset($contacts[$r['contact_name']])) $contacts[$r['contact_name']]=$r['contact_address']; $stmt->close();
function vendor_profile_h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$success=(string)($_SESSION['vendor_profile_success']??''); $error=(string)($_SESSION['vendor_profile_error']??''); unset($_SESSION['vendor_profile_success'],$_SESSION['vendor_profile_error']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CnrByte | Business Profile & Settings</title><link rel="stylesheet" href="/cnrbyte/css/header.css"><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"><link rel="stylesheet" href="/cnrbyte/css/forms.css"></head><body>
<?php require __DIR__.'/../includes/header.php'; ?>
<main class="cnr-page-container cnr-vendor-settings">
<section class="cnr-form-card"><h1>Business profile & settings</h1><p>Manage the selected business details shown to customers. Changes are restricted to this vendor workspace.</p>
<?php if($success!==''): ?><p class="success-title" role="status"><?=vendor_profile_h($success)?></p><?php endif; ?><?php if($error!==''): ?><p class="error-title" role="alert"><?=vendor_profile_h($error)?></p><?php endif; ?>
<div class="cnr-module-nav"><a href="/cnrbyte/vendors/dashboard.pge.php">Business dashboard</a><a href="/cnrbyte/messages/dashboard.pge.php">Messages & notifications</a><a href="/cnrbyte/clients/profile.pge.php">Personal account, contact & password settings</a></div>
<form method="post" action="/cnrbyte/vendors/profile.run.php" autocomplete="on" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?=vendor_profile_h($_SESSION['csrf_token']??'')?>">
<h2>Business registration</h2>
<label>Registered business name<input name="reg_name" value="<?=vendor_profile_h($vendor['reg_name']??'')?>" maxlength="180" required></label>
<label>Registration number<input name="reg_no" value="<?=vendor_profile_h($vendor['reg_no']??'')?>" maxlength="120"></label>
<label>Tax / VAT number<input name="tax_no" value="<?=vendor_profile_h($vendor['tax_no']??'')?>" maxlength="120"></label>
<label>Business email<input type="email" name="email" value="<?=vendor_profile_h($vendor['email']??'')?>" maxlength="150"></label>
<label>Business phone<input type="tel" name="phone" value="<?=vendor_profile_h($vendor['phone']??'')?>" maxlength="50"></label>
<label>Market<input name="market" value="<?=vendor_profile_h($vendor['market']??'local')?>" maxlength="120"></label>
<label>Registration date<input type="date" name="reg_date" value="<?=vendor_profile_h($vendor['reg_date']??'')?>"></label>
<label>Business start date<input type="date" name="starting_date" value="<?=vendor_profile_h($vendor['starting_date']??'')?>"></label>
<label>Business type<input name="business_type" value="<?=vendor_profile_h($vendor['business_type']??'')?>" maxlength="120"></label>
<label>Form of ownership<input name="business_form" value="<?=vendor_profile_h($vendor['business_form']??'')?>" maxlength="120"></label>
<label>Operation status<select name="operation_status"><option value="">Select status</option><?php foreach(['In Business','Temporarily Closed','Deregistered'] as $v): ?><option value="<?=vendor_profile_h($v)?>" <?=((string)($vendor['operation_status']??'')===$v?'selected':'')?>><?=vendor_profile_h($v)?></option><?php endforeach; ?></select></label>
<label>Financial year end<input type="date" name="fin_year_end" value="<?=vendor_profile_h($vendor['fin_year_end']??'')?>"></label>
<h2>Physical address</h2>
<label>Street address<input name="str_address" value="<?=vendor_profile_h($profile['str_address']??'')?>" maxlength="255" autocomplete="street-address"></label>
<label>Suburb<input name="phy_suburb" value="<?=vendor_profile_h($profile['phy_suburb']??'')?>" maxlength="150"></label>
<label>Town / city<input name="phy_town" value="<?=vendor_profile_h($profile['phy_town']??'')?>" maxlength="150" autocomplete="address-level2"></label>
<label>Province<input name="phy_province" value="<?=vendor_profile_h($profile['phy_province']??'')?>" maxlength="150"></label>
<label>Postal code<input name="phy_postal_code" value="<?=vendor_profile_h($profile['phy_postal_code']??'')?>" maxlength="50" autocomplete="postal-code"></label>
<h2>Postal address</h2>
<label>PO Box<input name="po_box" value="<?=vendor_profile_h($profile['po_box']??'')?>" maxlength="100"></label>
<label>Postal suburb<input name="pos_suburb" value="<?=vendor_profile_h($profile['pos_suburb']??'')?>" maxlength="150"></label>
<label>Postal town / city<input name="pos_town" value="<?=vendor_profile_h($profile['pos_town']??'')?>" maxlength="150"></label>
<label>Postal province<input name="pos_province" value="<?=vendor_profile_h($profile['pos_province']??'')?>" maxlength="150"></label>
<label>Postal code<input name="pos_postal_code" value="<?=vendor_profile_h($profile['pos_postal_code']??'')?>" maxlength="50"></label>
<h2>Public business profile</h2>
<label>Business logo (JPG, PNG or WebP)<input type="file" name="logo_img" accept=".jpg,.jpeg,.png,.webp"></label>
<label>About image (optional)<input type="file" name="about_img" accept=".jpg,.jpeg,.png,.webp"></label>
<label>Vision image (optional)<input type="file" name="vision_img" accept=".jpg,.jpeg,.png,.webp"></label>
<label>Mission image (optional)<input type="file" name="mission_img" accept=".jpg,.jpeg,.png,.webp"></label>
<label>Values image (optional)<input type="file" name="values_img" accept=".jpg,.jpeg,.png,.webp"></label>
<label>About the business<textarea name="about_para_text" rows="4" maxlength="5000"><?=vendor_profile_h($profile['about_para_text']??'')?></textarea></label>
<label>Vision<textarea name="vision_para_text" rows="3" maxlength="3000"><?=vendor_profile_h($profile['vision_para_text']??'')?></textarea></label>
<label>Mission<textarea name="mission_para_text" rows="3" maxlength="3000"><?=vendor_profile_h($profile['mission_para_text']??'')?></textarea></label>
<label>Values<textarea name="values_para_text" rows="3" maxlength="3000"><?=vendor_profile_h($profile['values_para_text']??'')?></textarea></label>
<h2>Customer contact channels</h2>
<label>Client care email or phone<input name="client_care" value="<?=vendor_profile_h($contacts['Client Care']??'')?>" maxlength="255"></label>
<label>Website<input name="website" value="<?=vendor_profile_h($contacts['Website']??'')?>" maxlength="255" placeholder="https://example.co.za"></label>
<p class="cnr-form-note">Your personal username, personal contact details and password are managed separately under Personal account settings.</p>
<button class="cnr-button" type="submit">Save business profile</button>
</form></section></main></body></html>
