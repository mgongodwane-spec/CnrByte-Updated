<?php
// Establish the session and enforce access before loading renderers that may output HTML.
require_once __DIR__ . '/../includes/session.run.php';
if (empty($_SESSION['user_info']['id'])) {
    header('Location: /cnrbyte/signup_login/signup_login.pge.php');
    exit;
}
// Keep the client workspace distinct from any previously selected vendor workspace.
unset($_SESSION['vendor_info'], $_SESSION['branch_info'], $_SESSION['shift_info'], $_SESSION['staff_info']);
require_once __DIR__ . '/../includes/global.shw.php';

// 2. Set Dashboard Context
$_SESSION['current_dashboard'] = 'clients_dashboard';
$_SESSION["selected_pge"] = "dashboard_pge";
$isAdmin = (($_SESSION['user_info']['rank'] ?? $_SESSION['rank'] ?? 'guest') === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNRBYTE - Client Dashboard</title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css">
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>


    <?php
    // 3.
    ?>

    <div class="clients_dashboard_body" style="padding: 20px; max-width: 1200px; margin: 0 auto;">
        <?php if ($isAdmin): ?>
            <div style="margin-bottom: 16px;">
                <a href="../admin/dashboard.pge.php" class="form-btn" style="text-decoration:none; display:inline-block;">Admin Dashboard</a>
            </div>
        <?php endif; ?>
        <h2 style="color: #034e22; margin-bottom: 20px;">Client Dashboard</h2>
        <section class="cnr-dashboard-cards">
            <a class="cnr-dashboard-card" href="/cnrbyte/clients/profile.pge.php" style="text-decoration:none;color:inherit"><strong>Profile &amp; Settings</strong><span>Change username, email, phone, physical address, postal address and password.</span></a>
            <a class="cnr-dashboard-card" href="/cnrbyte/marketplace/dashboard.pge.php" style="text-decoration:none;color:inherit"><strong>Marketplace</strong><span>Browse products and services.</span></a>
            <a class="cnr-dashboard-card" href="/cnrbyte/marketplace/marketplace_orders.php" style="text-decoration:none;color:inherit"><strong>My Orders</strong><span>Review your marketplace orders.</span></a>
            <a class="cnr-dashboard-card" href="/cnrbyte/marketplace/products/cart/dashboard.pge.php" style="text-decoration:none;color:inherit"><strong>Shopping Cart</strong><span>Review items before checkout.</span></a>
            <a class="cnr-dashboard-card" href="/cnrbyte/financial/banking/dashboard.pge.php" style="text-decoration:none;color:inherit"><strong>Banking</strong><span>Manage banking details and related records.</span></a>
            <a class="cnr-dashboard-card" href="/cnrbyte/financial/banking/statements/dashboard.pge.php" style="text-decoration:none;color:inherit"><strong>Statements</strong><span>View statement-related records.</span></a>
            <a class="cnr-dashboard-card" href="/cnrbyte/financial/dashboard.pge.php" style="text-decoration:none;color:inherit"><strong>Financials</strong><span>Open your financial workspace.</span></a>
            <a class="cnr-dashboard-card" href="/cnrbyte/messages/dashboard.pge.php" style="text-decoration:none;color:inherit"><strong>Messages &amp; Notifications</strong><span>Read and reply to client/vendor order conversations and important notifications.</span></a>
            <a class="cnr-dashboard-card" href="/cnrbyte/messages/chat.pge.php" style="text-decoration:none;color:inherit"><strong>Direct Chats</strong><span>Find clients, save contacts, and send or reply to direct messages.</span></a>
        </section>
    </div>

</body>
</html>
