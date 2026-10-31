<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
if (empty($_SESSION['user_info']['id'])) {
    header('Location: /cnrbyte/signup_login/signup_login.pge.php');
    exit;
}
// Keep the client workspace distinct from any previously selected vendor workspace.
unset($_SESSION['vendor_info'], $_SESSION['branch_info'], $_SESSION['shift_info'], $_SESSION['staff_info']);
require_once __DIR__ . '/../includes/conn.dbh.php';
$_SESSION['current_dashboard'] = 'clients_dashboard';
$_SESSION['selected_pge'] = 'profile_pge';
$clientId = (int)$_SESSION['user_info']['id'];
$stmt = $conn->prepare('SELECT id, username, email, phone FROM clients WHERE id=? LIMIT 1');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$client) { http_response_code(404); exit('Account not found.'); }
$stmt = $conn->prepare('SELECT str_address, phy_suburb, phy_town, phy_province, phy_postal_code, po_box, pos_suburb, pos_town, pos_province, pos_postal_code FROM profiles WHERE client_id=? ORDER BY id DESC LIMIT 1');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
function profile_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$success = (string)($_SESSION['client_profile_success'] ?? '');
$error = (string)($_SESSION['client_profile_error'] ?? '');
unset($_SESSION['client_profile_success'], $_SESSION['client_profile_error']);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CnrByte | Profile & Contact Details</title><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"></head><body>
<?php require $_SERVER['DOCUMENT_ROOT'].'/cnrbyte/includes/show/header.php'; ?>
<main class="cnr-form-card">
  <h2>Personal settings</h2>
  <p>Keep your contact details and delivery/postal addresses up to date. These details are private to your account.</p>
  <?php if ($success !== ''): ?><p class="success-title" role="status"><?= profile_h($success) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="error-title" role="alert"><?= profile_h($error) ?></p><?php endif; ?>
  <form method="post" action="/cnrbyte/clients/profile.run.php" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= profile_h($_SESSION['csrf_token'] ?? '') ?>">
    <label>Username<input name="username" value="<?= profile_h($client['username']) ?>" minlength="3" maxlength="100" required autocomplete="username"></label>
    <label>Email address<input type="email" name="email" value="<?= profile_h($client['email']) ?>" maxlength="150" required autocomplete="email"></label>
    <label>Mobile / contact number<input type="tel" name="phone" value="<?= profile_h($client['phone']) ?>" maxlength="50" required autocomplete="tel"></label>
    <label>Physical street address<input name="str_address" value="<?= profile_h($profile['str_address'] ?? '') ?>" maxlength="255" autocomplete="street-address"></label>
    <label>Suburb<input name="phy_suburb" value="<?= profile_h($profile['phy_suburb'] ?? '') ?>" maxlength="150"></label>
    <label>Town / city<input name="phy_town" value="<?= profile_h($profile['phy_town'] ?? '') ?>" maxlength="150" autocomplete="address-level2"></label>
    <label>Province<input name="phy_province" value="<?= profile_h($profile['phy_province'] ?? '') ?>" maxlength="150" autocomplete="address-level1"></label>
    <label>Physical postal code<input name="phy_postal_code" value="<?= profile_h($profile['phy_postal_code'] ?? '') ?>" maxlength="50" autocomplete="postal-code"></label>
    <label>Postal address / PO Box<input name="po_box" value="<?= profile_h($profile['po_box'] ?? '') ?>" maxlength="100"></label>
    <label>Postal suburb<input name="pos_suburb" value="<?= profile_h($profile['pos_suburb'] ?? '') ?>" maxlength="150"></label>
    <label>Postal town / city<input name="pos_town" value="<?= profile_h($profile['pos_town'] ?? '') ?>" maxlength="150"></label>
    <label>Postal province<input name="pos_province" value="<?= profile_h($profile['pos_province'] ?? '') ?>" maxlength="150"></label>
    <label>Postal code<input name="pos_postal_code" value="<?= profile_h($profile['pos_postal_code'] ?? '') ?>" maxlength="50"></label>
    <div style="grid-column:1/-1;border-top:1px solid var(--cnr-border);padding-top:14px"><h3>Change password (optional)</h3><p>Leave all password fields blank if you do not want to change it. Use at least 12 characters.</p></div>
    <label>Current password<input type="password" name="current_password" autocomplete="current-password"></label>
    <label>New password<input type="password" name="new_password" minlength="12" maxlength="4096" autocomplete="new-password"></label>
    <label>Confirm new password<input type="password" name="confirm_password" minlength="12" maxlength="4096" autocomplete="new-password"></label>
    <button class="cnr-button" type="submit">Save profile and contact details</button>
  </form>
  <p><a class="cnr-header-link" href="/cnrbyte/clients/dashboard.pge.php">Return to client dashboard</a> <a class="cnr-header-link" href="/cnrbyte/messages/dashboard.pge.php">Open messages</a></p>
</main></body></html>
