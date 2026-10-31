<?php
require_once __DIR__ . '/../includes/session.run.php';
// Enforce access before loading renderers that may output HTML.
if (!in_array(strtolower((string)($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? '')), ['admin', 'administrator'], true)) {
    header('Location: /cnrbyte/signup_login/signup_login.pge.php');
    exit;
}
require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/global.dbh.php';
require_once __DIR__ . '/../includes/global.shw.php';
require_once __DIR__ . '/admin.shw.php';
unset($_SESSION['current_dashboard']);
$_SESSION['current_dashboard'] = 'admin_dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/hierarchy.css">
    <link rel="stylesheet" href="/cnrbyte/css/menu.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <style>.cnr-admin-card{max-width:1200px;margin:24px auto;padding:20px;background:#fff;border:1px solid #dce3e8;border-radius:12px;box-shadow:0 3px 14px rgba(0,0,0,.06);text-align:left}.cnr-admin-card h3{margin-top:0}.cnr-table-wrap{width:100%;overflow-x:auto}.cnr-admin-card table{width:100%;border-collapse:collapse}.cnr-admin-card th,.cnr-admin-card td{padding:10px 12px;border-bottom:1px solid #e4e9ed;text-align:left;vertical-align:top}.cnr-admin-card th{background:#f4f7f9}.cnr-approval-form{margin:0}.cnr-approval-form .form-btn{white-space:nowrap}</style>

</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <div class="dashboard_body">
        <h2>Admin Dashboard</h2>
        <?php
        $approvalState = (string)($_GET['vendor_approval'] ?? '');
        if ($approvalState === 'approved') echo '<p class="success-title" role="status">Vendor approved successfully.</p>';
        elseif ($approvalState === 'failed') echo '<p class="error-title" role="alert">Vendor approval did not complete. The vendor may already have been processed.</p>';
        elseif ($approvalState === 'invalid') echo '<p class="error-title" role="alert">A valid vendor registration was not selected.</p>';
        ?>
        <?php if (in_array(strtolower((string)($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? '')), ['admin', 'administrator'], true)): ?>
            <p><a href="/cnrbyte/admin/marketplace_lifecycle.pge.php">Marketplace refunds, vendor settlements & reconciliation</a></p>
        <?php endif; ?>
        <?php
            // hierachy
            show_hierarchy($conn);
            show_pending_vendor_approvals($conn);
        ?>
    </div>
</body>
</html>
