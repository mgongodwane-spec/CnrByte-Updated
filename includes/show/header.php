<?php
// Ensure session is started before this file is included
$isLoggedIn = isset($_SESSION['user_info']) && is_array($_SESSION['user_info']);
if (!$isLoggedIn && isset($_SESSION['user_id'], $_SESSION['username'])) {
    $isLoggedIn = true;
}

// 1. User Context & Setup
$userRank = $_SESSION['user_info']['rank'] ?? ($_SESSION['rank'] ?? 'guest');
$userRank = strtolower(str_replace(' ', '_', trim((string)$userRank)));
$currentDashboard = $_SESSION['current_dashboard'] ?? 'home_dashboard';
$profileImg = $_SESSION['user_info']['profile_img'] ?? 'default.png';
$username = $_SESSION['user_info']['username'] ?? ($_SESSION['username'] ?? 'CnrByte');
if (isset($_SESSION['vendor_info']['reg_name']) && in_array($currentDashboard, ['vendors_dashboard','branches_dashboard','shifts_dashboard','staff_dashboard','hr_dashboard','messages_dashboard'], true)) {
    $username = (string)$_SESSION['vendor_info']['reg_name'];
}
$clientId = (int)($_SESSION['user_info']['id'] ?? 0);

// 2. Define Dashboard Menus
$dashboardMenus = [
    'products_dashboard' => [
        'roles' => ['vendor', 'vendor_admin', 'admin', 'client'],
        'items' => [
            'Home' => '', // Set dynamically below
            'Products' => '/cnrbyte/marketplace/products/dashboard.pge.php',
            'Create' => '/cnrbyte/marketplace/products/create.pge.php',
            'Edit' => '/cnrbyte/marketplace/products/edit.pge.php',
            'Vendors' => '/cnrbyte/vendors/dashboard.pge.php',
            'Marketplace' => '/cnrbyte/marketplace/dashboard.pge.php',
            'Settings' => '/cnrbyte/settings/settings.pge.php'
        ]
    ],
    'staff_dashboard' => [
        'roles' => ['vendor', 'vendor_admin', 'admin', 'client'],
        'items' => [
            'Home' => '', // Set dynamically below
            'Staff' => '/cnrbyte/vendors/branches/shifts/staff/dashboard.pge.php',
            'Create' => '/cnrbyte/vendors/branches/shifts/staff/create.pge.php',
            'Edit' => '/cnrbyte/vendors/branches/shifts/staff/edit.pge.php',
            'Branches' => '/cnrbyte/vendors/branches/dashboard.pge.php',
            'Shifts' => '/cnrbyte/vendors/branches/shifts/dashboard.pge.php',
            'HR' => '/cnrbyte/vendors/branches/shifts/staff/hr/dashboard.pge.php',
            'Settings' => '/cnrbyte/settings/settings.pge.php'
        ]
    ],
    'branches_dashboard' => [
        'roles' => ['vendor', 'vendor_admin', 'admin', 'client'],
        'items' => [
            'Home' => '', // Set dynamically below
            'Branches' => '/cnrbyte/vendors/branches/dashboard.pge.php',
            'Create' => '/cnrbyte/vendors/branches/create.pge.php',
            'Edit' => '/cnrbyte/vendors/branches/edit.pge.php',
            'Shifts' => '/cnrbyte/vendors/branches/shifts/edit.pge.php',
            'Staff' => '/cnrbyte/vendors/branches/shifts/staff/edit.pge.php',
            'HR' => '/cnrbyte/vendors/branches/shifts/staff/hr/edit.pge.php',
            'Settings' => '/cnrbyte/settings/settings.pge.php'
        ]
    ],
    'hr_dashboard' => [
        'roles' => ['vendor', 'vendor_admin', 'admin', 'client'],
        'items' => [
            'Home' => '', // Set dynamically below
            'HR' => '/cnrbyte/vendors/branches/shifts/staff/hr/dashboard.pge.php',
            'Create' => '/cnrbyte/vendors/branches/shifts/staff/hr/create.pge.php',
            'Edit' => '/cnrbyte/vendors/branches/shifts/staff/hr/edit.pge.php',
            'Branches' => '/cnrbyte/vendors/branches/dashboard.pge.php',
            'Shifts' => '/cnrbyte/vendors/branches/shifts/dashboard.pge.php',
            'Staff' => '/cnrbyte/vendors/branches/shifts/staff/dashboard.pge.php',
            'Settings' => '/cnrbyte/settings/settings.pge.php'
        ]
    ],
    'shifts_dashboard' => [
        'roles' => ['vendor', 'vendor_admin', 'admin', 'client'],
        'items' => [
            'Home' => '', // Set dynamically below
            'Shifts' => '/cnrbyte/vendors/branches/shifts/dashboard.pge.php',
            'Create' => '/cnrbyte/vendors/branches/shifts/create.pge.php',
            'Edit' => '/cnrbyte/vendors/branches/shifts/edit.pge.php',
            'Branches' => '/cnrbyte/vendors/branches/dashboard.pge.php',
            'Staff' => '/cnrbyte/vendors/branches/shifts/staff/dashboard.pge.php',
            'HR' => '/cnrbyte/vendors/branches/shifts/staff/hr/dashboard.pge.php',
            'Settings' => '/cnrbyte/settings/settings.pge.php'
        ]
    ],
    'banking_dashboard' => [
        'roles' => ['admin', 'vendor_admin', 'vendor', 'client'],
        'items' => [
            'Home' => '',
            'Banking' => '/cnrbyte/financial/banking/dashboard.pge.php',
            'Create' => '/cnrbyte/financial/banking/create.pge.php',
            'Edit' => '/cnrbyte/financial/banking/edit.pge.php',
            'Settings' => '/cnrbyte/settings/settings.pge.php'
        ]
    ],
    'financial_dashboard' => [
        'roles' => ['admin', 'vendor_admin', 'vendor', 'client'],
        'items' => [
            'Home' => '',
            'Financials' => '/cnrbyte/financial/dashboard.pge.php',
            'Create' => '/cnrbyte/financial/create.pge.php',
            'Edit' => '/cnrbyte/financial/edit.pge.php'
        ]
    ],
    'messages_dashboard' => [
        'roles' => ['admin', 'vendor_admin', 'vendor', 'client'],
        'items' => [
            'Home' => '',
            'Messages' => '/cnrbyte/messages/dashboard.pge.php',
            'Chat' => '/cnrbyte/messages/chat.pge.php',
            'Create' => '/cnrbyte/messages/create.pge.php',
            'Edit' => '/cnrbyte/messages/edit.pge.php'
        ]
    ],
    'vendors_dashboard' => [
        'roles' => ['client', 'vendor', 'vendor_admin', 'admin'],
        'items' => [
            'Home' => '',
            'Vendors' => '/cnrbyte/vendors/dashboard.pge.php',
            'Create' => '/cnrbyte/vendors/create.pge.php',
            'Edit ' => '/cnrbyte/vendors/edit.pge.php',
            'Marketplace' => '/cnrbyte/marketplace/dashboard.pge.php',
            'Package Orders' => '/cnrbyte/marketplace/products/qr_code/vendor_packages.pge.php',
            'Products' => '/cnrbyte/marketplace/products/dashboard.pge.php',
            'Branches' => '/cnrbyte/vendors/branches/dashboard.pge.php',
            'Shifts' => '/cnrbyte/vendors/branches/shifts/dashboard.pge.php',
            'Staff' => '/cnrbyte/vendors/branches/shifts/staff/dashboard.pge.php',
            'HR' => '/cnrbyte/vendors/branches/shifts/staff/hr/dashboard.pge.php',
            'Settings' => '/cnrbyte/settings/settings.pge.php'
        ]
    ],
    'clients_dashboard' => [
        'roles' => ['admin', 'vendor_admin', 'vendor', 'client'],
        'items' => [
            'Home' => '',
            'Dashboard' => '/cnrbyte/clients/dashboard.pge.php',
            'Marketplace' => '/cnrbyte/marketplace/dashboard.pge.php',
            'Banking' => '/cnrbyte/financial/banking/dashboard.pge.php',
            'Statements' => '/cnrbyte/financial/banking/statements/dashboard.pge.php',
            'Messages' => '/cnrbyte/messages/dashboard.pge.php',
            'Edit Profile' => '/cnrbyte/clients/edit.pge.php'
        ]
    ],
    'marketplace_dashboard' => [
        'roles' => ['admin', 'vendor_admin', 'vendor', 'client'],
        'items' => [
            'Home' => '',
            'Marketplace' => '/cnrbyte/marketplace/dashboard.pge.php',
            'Package Orders' => '/cnrbyte/marketplace/products/qr_code/vendor_packages.pge.php',
            'Quotation Builder' => '/cnrbyte/marketplace/products/quotation/quotation.pge.php',
            'Settings' => '/cnrbyte/settings/settings.pge.php'
        ]
    ],
    'guest' => [
        'roles' => ['guest'],
        'items' => [
            'Home' => '/cnrbyte/signup_login/signup_login.pge.php',
            'About' => '/cnrbyte/signup_login/signup_login.pge.php',
            'Contact Us' => '/cnrbyte/signup_login/signup_login.pge.php',
            'Login / Signup' => '/cnrbyte/signup_login/signup_login.pge.php'
        ]
    ]
];

// 3. Select the correct Menu based on Dashboard and Role
$menuItems = [];
foreach ($dashboardMenus as $dbKey => $data) {
    if ($dbKey === $currentDashboard && in_array($userRank, $data['roles'])) {
        $menuItems = $data['items'];
        break;
    }
}

// Fallback to Guest if nothing matches
if (empty($menuItems)) {
    $menuItems = $dashboardMenus['guest']['items'];
}

// 4. FIX: Core Home Button Logic
if (isset($menuItems['Home'])) {
    if ($currentDashboard === 'clients_dashboard') {
        // If we're already home, go to landing page
        $menuItems['Home'] = '/cnrbyte/clients/dashboard.pge.php';
    } else {
        // From any other dashboard, "Home" is the Client Dashboard
        $menuItems['Home'] = '/cnrbyte/clients/dashboard.pge.php';
    }
}

// 5. Vendor Dashboard Shortcut (For Clients who own Vendors)
if ($isLoggedIn && $currentDashboard === 'clients_dashboard' && $clientId > 0 && isset($conn)) {
    $vCheck = $conn->prepare("SELECT id FROM vendors WHERE client_id = ? LIMIT 1");
    if ($vCheck) {
        $vCheck->bind_param("i", $clientId);
        $vCheck->execute();
        if ($vCheck->get_result()->fetch_assoc()) {
            // Insert after "Clients Dashboard"
            $menuItems = array_slice($menuItems, 0, 2, true)
                + ['Vendors' => '/cnrbyte/vendors/dashboard.pge.php']
                + array_slice($menuItems, 2, null, true);
        }
        $vCheck->close();
    }
}

// Use stable, existing project routes for the primary role-specific navigation.
if ($isLoggedIn && isset($_SESSION['vendor_info']['id']) && in_array($currentDashboard, ['vendors_dashboard','branches_dashboard','shifts_dashboard','staff_dashboard','hr_dashboard','messages_dashboard'], true)) {
    $menuItems = [
        'Vendor Dashboard' => '/cnrbyte/vendors/dashboard.pge.php',
        'Business Profile' => '/cnrbyte/vendors/profile.pge.php',
        'Personal Settings' => '/cnrbyte/clients/profile.pge.php',
        'Branches' => '/cnrbyte/vendors/branches/dashboard.pge.php',
        'Shifts' => '/cnrbyte/vendors/branches/shifts/dashboard.pge.php',
        'Staff' => '/cnrbyte/vendors/branches/shifts/staff/dashboard.pge.php',
        'HR & Payroll' => '/cnrbyte/vendors/branches/shifts/staff/hr/dashboard.pge.php',
        'Products' => '/cnrbyte/marketplace/products/dashboard.pge.php',
        'Marketplace' => '/cnrbyte/marketplace/dashboard.pge.php',
        'Messages' => '/cnrbyte/messages/dashboard.pge.php',
        'Customer Chats' => '/cnrbyte/messages/vendor_chat.pge.php',
    ];
} elseif ($isLoggedIn && in_array($currentDashboard, ['clients_dashboard','messages_dashboard'], true)) {
    $menuItems = [
        'Client Dashboard' => '/cnrbyte/clients/dashboard.pge.php',
        'Marketplace' => '/cnrbyte/marketplace/dashboard.pge.php',
        'My Orders' => '/cnrbyte/marketplace/marketplace_orders.php',
        'Messages' => '/cnrbyte/messages/dashboard.pge.php',
        'Direct Chats' => '/cnrbyte/messages/chat.pge.php',
        'Profile & Contact Details' => '/cnrbyte/clients/profile.pge.php',
    ];
}

// Unread counters are optional until the Step 13 messaging migration is applied.
$unreadMessages = 0;
$unreadNotifications = 0;
if ($isLoggedIn && isset($conn) && $conn instanceof mysqli) {
    $recipientType = isset($_SESSION['vendor_info']['id']) && in_array($currentDashboard, ['vendors_dashboard','branches_dashboard','shifts_dashboard','staff_dashboard','hr_dashboard','messages_dashboard'], true) ? 'vendor' : 'client';
    $recipientId = $recipientType === 'vendor' ? (int)$_SESSION['vendor_info']['id'] : $clientId;
    try {
        $check = $conn->query("SHOW TABLES LIKE 'cnrbyte_messages'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM cnrbyte_messages WHERE recipient_type=? AND recipient_id=? AND is_read=0');
            $stmt->bind_param('si', $recipientType, $recipientId); $stmt->execute();
            $unreadMessages = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0); $stmt->close();
        }
        $check = $conn->query("SHOW TABLES LIKE 'cnrbyte_notifications'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM cnrbyte_notifications WHERE recipient_type=? AND recipient_id=? AND is_read=0');
            $stmt->bind_param('si', $recipientType, $recipientId); $stmt->execute();
            $unreadNotifications = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0); $stmt->close();
        }
        $check = $conn->query("SHOW TABLES LIKE 'messages'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM messages WHERE receiver_id=? AND is_read=0');
            $stmt->bind_param('i', $clientId); $stmt->execute();
            $unreadNotifications += (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0); $stmt->close();
        }
    } catch (Throwable $e) {
        // Keep navigation available if optional messaging tables are not migrated yet.
    }
}

// 6. Handle Auth Links
// Add QR Shop link available on all dashboards
$menuItems['QR Shop'] = !empty($_SESSION['vendor_info']['id']) ? '/cnrbyte/marketplace/products/qr_code/vendor_packages.pge.php' : '/cnrbyte/marketplace/dashboard.pge.php';
unset($menuItems['Login / Signup'], $menuItems['Log Out']);
if (!$isLoggedIn) {
    $menuItems['Login / Signup'] = '/cnrbyte/signup_login/signup_login.pge.php';
}
?>

<link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css">
<div class="header cnr-header">
    <a class="cnr-brand" href="/cnrbyte/clients/dashboard.pge.php" aria-label="CnrByte home">CnrByte</a>
    <div class="cnr-identity">
        <span class="cnr-identity-label"><?= isset($_SESSION['vendor_info']['reg_name']) && in_array($currentDashboard, ['vendors_dashboard','branches_dashboard','shifts_dashboard','staff_dashboard','hr_dashboard','messages_dashboard'], true) ? 'Vendor' : 'User' ?></span>
        <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong>
        <?= $isLoggedIn ? '<span class="rank-tag">' . htmlspecialchars(ucwords(str_replace('_', ' ', $userRank)), ENT_QUOTES, 'UTF-8') . '</span>' : '' ?>
    </div>
    <nav class="cnr-header-actions" aria-label="Account navigation">
        <?php if ($isLoggedIn): ?>
            <a class="cnr-header-link" href="/cnrbyte/messages/dashboard.pge.php">Messages<?= $unreadMessages > 0 ? " (" . $unreadMessages . ")" : "" ?></a>
            <a class="cnr-header-link" href="/cnrbyte/messages/dashboard.pge.php#notifications">Notifications<?= $unreadNotifications > 0 ? " (" . $unreadNotifications . ")" : "" ?></a>
            <form method="post" action="/cnrbyte/signup_login/logout.run.php" class="cnr-logout-form" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="cnr-header-link" style="background:none;border:0;cursor:pointer">Log out</button>
            </form>
        <?php else: ?>
            <a class="cnr-header-link" href="/cnrbyte/signup_login/signup_login.pge.php">Log in</a>
        <?php endif; ?>
    </nav>
</div>

<div class="menu">
    <?php foreach ($menuItems as $label => $link): ?>
        <?php if (!empty($link)): ?>
            <a class="menu-btn" href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<style>
    /* Quick fix for the inline forms to keep buttons side-by-side */
    .menu { text-align: center; padding: 10px; background: #f8f9fa; }
    .menu-form { display: inline; }
    .rank-tag { font-size: 0.8em; color: #555; }
</style>
