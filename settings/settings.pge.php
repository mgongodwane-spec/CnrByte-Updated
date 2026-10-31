<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/global.shw.php';
require_once __DIR__ . '/settings.func.php';
require_once __DIR__ . '/settings.shw.php';

if (empty($_SESSION['user_info']['id'])) {
    header('Location: /cnrbyte/signup_login/signup_login.pge.php');
    exit;
}

// Context setting
$_SESSION['current_dashboard'] = 'settings_dashboard';
$_SESSION['selected_pge'] = 'settings_pge';

$userRank = strtolower((string)($_SESSION['user_info']['rank'] ?? 'guest'));
$isAdmin = in_array($userRank, ['admin', 'administrator'], true);
$allowedSelectedTypes = ['business_type','bank_name','acc_type','contract_type','leave_type','earnings_type','deductions_type','document_type','qualification_type','share_type','occupation_type'];
$requestedType = (string)($_GET['type'] ?? '');
$selectedType = in_array($requestedType, $allowedSelectedTypes, true) ? $requestedType : '';
$settingsLookupOwnerId = (int)($_SESSION['user_info']['id'] ?? 0);
if (isset($_SESSION['vendor_info']['id'])) {
    $activeVendorId = (int)$_SESSION['vendor_info']['id'];
    $ownerStmt = $conn->prepare("SELECT COALESCE(client_id, user_id) AS owner_id FROM vendors WHERE id = ? AND status = 'approved' LIMIT 1");
    if ($ownerStmt) { $ownerStmt->bind_param('i', $activeVendorId); $ownerStmt->execute(); $ownerRow = $ownerStmt->get_result()->fetch_assoc(); $ownerStmt->close(); if ($ownerRow && (int)$ownerRow['owner_id'] > 0) $settingsLookupOwnerId = (int)$ownerRow['owner_id']; }
}

/** * SECURITY CHECK:
 * Verify the user is a logged-in client AND owns the currently active vendor.
 */
$isAuthorizedOwner = false;
if (isset($_SESSION['user_info']['id']) && isset($_SESSION['vendor_info']['id'])) {
    $sessionVendorOwner = (int)($_SESSION['vendor_info']['client_id'] ?? $_SESSION['vendor_info']['user_id'] ?? 0);
    if ((int)$_SESSION['user_info']['id'] === $sessionVendorOwner) {
        $isAuthorizedOwner = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings DashBoard</title>
    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/vendor_profile.css">
    <link rel="stylesheet" href="/cnrbyte/css/hierarchy.css">
    <link rel="stylesheet" href="/cnrbyte/css/menu.css">
    <style>.settings_body{max-width:1400px;margin:0 auto;padding:24px 16px 50px}.settings-actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-bottom:24px}.settings-grid{display:flex;flex-wrap:wrap;justify-content:center;align-items:flex-start;gap:20px}.settings_body .create_products_box{width:min(100%,420px);box-sizing:border-box}.settings_body .signup-form{height:100%}.settings_body label{display:block;text-align:left;margin-top:12px}.settings_body input,.settings_body select,.settings_body textarea{box-sizing:border-box;width:100%;max-width:100%;padding:10px;border:1px solid #cbd5dc;border-radius:6px}.settings_body textarea{min-height:80px}.settings_body .form-btn{display:inline-block;text-decoration:none;border:0;cursor:pointer}</style>
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <?php  ?>

    <div class="settings_body" style="text-align: center; padding-bottom: 50px;">
        <h2>Settings</h2>
        <?php settings_render_flash_messages(); ?>

        <?php if ($userRank !== 'client') : ?>
            <div class="settings-actions">
                <a class="form-btn" href="/cnrbyte/settings/settings.pge.php">Refresh</a>
                <a class="form-btn" href="/cnrbyte/vendors/dashboard.pge.php">Vendors</a>
                <a class="form-btn" href="/cnrbyte/vendors/branches/shifts/staff/dashboard.pge.php">Staff</a>
                <?php if ($isAdmin) : ?>
                    <a class="form-btn" href="/cnrbyte/admin/dashboard.pge.php">Admin</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">

            <?php if ($userRank != 'guest') : ?>
                <!-- INSERT OR EDIT TYPES -->
                <div class="create_products_box">
                    <form action="../settings/settings.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <h3 class="form-title">Add Lookup Types</h3>
                        <label for="type">Type Category</label>
                        <select id="type" name="type" required>
                            <option value="">--- Select Type ---</option>
                            <optgroup label="Vendors">
                                <option value="business_type" <?php echo $selectedType === 'business_type' ? 'selected' : ''; ?>>Business Type</option>
                                <option value="bank_name" <?php echo $selectedType === 'bank_name' ? 'selected' : ''; ?>>Bank Name</option>
                                <option value="acc_type" <?php echo $selectedType === 'acc_type' ? 'selected' : ''; ?>>Account Type</option>
                            </optgroup>
                            <optgroup label="Staff & HR">
                                <option value="contract_type" <?php echo $selectedType === 'contract_type' ? 'selected' : ''; ?>>Contract</option>
                                <option value="leave_type" <?php echo $selectedType === 'leave_type' ? 'selected' : ''; ?>>Leave</option>
                                <option value="earnings_type" <?php echo $selectedType === 'earnings_type' ? 'selected' : ''; ?>>Earning</option>
                                <option value="deductions_type" <?php echo $selectedType === 'deductions_type' ? 'selected' : ''; ?>>Deduction</option>
                                <option value="document_type" <?php echo $selectedType === 'document_type' ? 'selected' : ''; ?>>Official Document</option>
                                <option value="qualification_type" <?php echo $selectedType === 'qualification_type' ? 'selected' : ''; ?>>Qualification</option>
                                <option value="occupation_type" <?php echo $selectedType === 'occupation_type' ? 'selected' : ''; ?>>Occupation</option>
                                <option value="share_type" <?php echo $selectedType === 'share_type' ? 'selected' : ''; ?>>Share Type</option>
                            </optgroup>
                        </select>
                        <label for="type_name">Type Name</label>
                        <input id="type_name" type="text" name="type_name" maxlength="180" required>
                        <button type="submit" name="vendor_type" class="form-btn">Add Type</button>
                    </form>
                </div>

                <!-- ORGANISATIONAL RANKS -->
                <?php if ($isAuthorizedOwner) : ?>
                    <div class="create_products_box">
                        <form action="../settings/settings.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <h3 class="form-title">Organisational Ranks</h3>
                            <input type="hidden" name="client_id" value="<?php echo (int)$_SESSION['user_info']['id']; ?>">
                            <input type="hidden" name="vendor_id" value="<?php echo (int)($_SESSION['vendor_info']['id'] ?? 0); ?>">

                            <label for="rank_name">Rank Name</label>
                            <input type="text" name="rank_name" required>
                            <label for="occupation">Occupation</label>
                            <select id="occupation" name="occupation" required><option value="">Select occupation</option><?php settings_render_lookup_options($conn, "occupation_type", $settingsLookupOwnerId); ?></select>
                            <label for="rank">Hierarchy Level</label>
                            <input id="rank" type="number" name="rank" min="1" max="100000" step="1" required>
                            <label for="rate">Default Pay Rate</label>
                            <input type="number" name="rate" min="0" step="0.01" value="0.00">
                            <button type="submit" name="insert_rank" class="form-btn">Add Rank</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($isAdmin) : ?>
                <!-- ADMIN DASHBOARD SETTINGS -->
                <div class="create_products_box">
                    <form action="../settings/settings.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <h3 class="form-title">Admin Dashboard Settings</h3>
                        <input type="hidden" name="current_dashboard" value="settings_dashboard">
                        <input type="hidden" name="selected_pge" value="settings_pge">

                        <label for="admin_object_type">Object Type</label>
                        <select id="admin_object_type" name="type" required>
                            <option value="form">Form</option>
                            <option value="table">Table</option>
                        </select>
                        <label for="admin_object_name">Object Name</label>
                        <input id="admin_object_name" type="text" name="type_name" maxlength="100" required>
                        <button type="submit" name="admin_type" class="form-btn">Add Configuration Item</button>
                    </form>
                </div>

                <!-- ADMIN FULL PRODUCT & QR CREATOR -->
                <div class="create_products_box">
                    <form action="../settings/settings.run.php" method="post" enctype="multipart/form-data" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <h3 class="form-title">Admin: Product & QR Hierarchy</h3>

                        <!-- QR SYNC BUTTON (NEW) -->
                        <div style="margin-bottom: 15px; padding: 10px; background: #e9ecef; border-radius: 5px;">
                            <p style="font-size: 0.8rem; color: #555; margin-bottom: 8px;">Missing QR codes for old products?</p>
                            <a href="/cnrbyte/marketplace/products/qr_code/sync_qrcodes.php" class="form-btn" style="background:#007bff; text-decoration:none; padding: 8px; font-size: 0.85rem;">
                                🔄 Sync & Repair QRs
                            </a>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px;">
                            <input type="text" name="great_name" placeholder="Great (e.g. Agric)" required>
                            <input type="text" name="grand_name" placeholder="Grand (e.g. Livestock)" required>
                            <input type="text" name="parent_name" placeholder="Parent (e.g. Poultry)" required>
                            <input type="text" name="child_name" placeholder="Child (e.g. Feed)" required>
                        </div>
                        <hr style="margin: 15px 0; border:0; border-top:1px solid #eee;">
                        <input type="text" name="product_name" maxlength="180" placeholder="Product Name" required>
                        <textarea name="description" maxlength="5000" placeholder="Description"></textarea>
                        <select name="measure">
                            <option value="kilograms">Kilograms</option>
                            <option value="pieces">Pieces</option>
                            <option value="litres">Litres</option>
                        </select>
                        <input type="file" name="product_img" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <label for="admin_product_vendor">Approved vendor</label>
                        <select id="admin_product_vendor" name="vendor_id" required>
                            <option value="">Select vendor</option>
                            <?php
                            $selectedVendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
                            $vendorOptions = $conn->query("SELECT id, reg_name FROM vendors WHERE status = 'approved' ORDER BY reg_name");
                            if ($vendorOptions) {
                                while ($vendorOption = $vendorOptions->fetch_assoc()) {
                                    $optionId = (int)$vendorOption['id'];
                                    $optionName = htmlspecialchars((string)$vendorOption['reg_name'], ENT_QUOTES, 'UTF-8');
                                    $selected = $optionId === $selectedVendorId ? ' selected' : '';
                                    echo '<option value="' . $optionId . '"' . $selected . '>' . $optionName . '</option>';
                                }
                                $vendorOptions->free();
                            }
                            ?>
                        </select>
                        <button type="submit" name="admin_create_full_product" class="form-btn" style="background:#034e22;">Create &amp; Generate QR</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
