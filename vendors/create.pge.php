<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
if(empty($_SESSION['user_info']['id'])){header('Location: /cnrbyte/signup_login/signup_login.pge.php');exit;}
$_SESSION['current_dashboard']='vendors_dashboard';
function vendor_create_h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$errors=$_SESSION['errors_create']??[];unset($_SESSION['errors_create']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CnrByte | Register a Business</title><link rel="stylesheet" href="/cnrbyte/css/header.css"><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"></head><body>
<?php require __DIR__.'/../includes/header.php'; ?>
<main class="cnr-page-container"><section class="cnr-form-card"><h1>Register a business</h1><p>Create a vendor workspace for your company. Once submitted, you can manage the profile, branches, staff, products, financials and customer messages from the vendor dashboard.</p>
<?php if($errors): ?><div class="error-title" role="alert"><strong>Please correct the following:</strong><ul><?php foreach($errors as $message): ?><li><?=vendor_create_h($message)?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/cnrbyte/vendors/forms.run.php" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=vendor_create_h($_SESSION['csrf_token']??'')?>">
<label>Registration certificate (JPG, PNG or PDF)<input type="file" name="reg_cert" accept=".jpg,.jpeg,.jfif,.png,.pdf" required></label>
<label>Registration number<input name="reg_no" maxlength="120" required></label><label>Registered business name<input name="reg_name" maxlength="180" required></label>
<label>Registration date<input type="date" name="reg_date" required></label><label>Business start date<input type="date" name="starting_date" required></label>
<label>Business type<input name="business_type" maxlength="120" required></label><label>Form of ownership<select name="business_form" required><option value="">Select</option><option>Private</option><option>Public</option><option>Cooperative</option><option>Partnership</option><option>Club</option></select></label>
<label>Operation status<select name="operation_status" required><option value="">Select</option><option>In Business</option><option>Deregistered</option></select></label><label>Financial year end<input type="date" name="fin_year_end" required></label>
<label>Tax / VAT number<input name="tax_no" maxlength="120" required></label><label>Business phone<input type="tel" name="phone" maxlength="50" required></label><label>Business email<input type="email" name="email" maxlength="150" required></label>
<button class="cnr-button" type="submit" name="create_new_vendor">Create business</button></form></section></main></body></html>
