<?php
declare(strict_types=1);

/**
 * CnrByte public entry point.
 * This page intentionally does not require a database connection so the landing
 * page remains available when optional marketplace/database services are down.
 */
require_once __DIR__ . '/includes/session.run.php';

function cnr_index_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$userInfo = isset($_SESSION['user_info']) && is_array($_SESSION['user_info']) ? $_SESSION['user_info'] : [];
$userId = (int)($userInfo['id'] ?? $_SESSION['user_id'] ?? 0);
$isLoggedIn = $userId > 0;
$rank = strtolower(trim((string)($userInfo['rank'] ?? $_SESSION['user_rank'] ?? $_SESSION['rank'] ?? 'client')));
$isAdmin = in_array($rank, ['admin', 'administrator'], true);
$activeVendor = isset($_SESSION['vendor_info']) && is_array($_SESSION['vendor_info'])
    ? $_SESSION['vendor_info']
    : [];
$isVendorWorkspace = $isLoggedIn && (int)($activeVendor['id'] ?? 0) > 0;
$username = trim((string)($userInfo['username'] ?? $_SESSION['username'] ?? ''));
if ($username === '') {
    $username = 'Account';
}
$vendorName = trim((string)($activeVendor['reg_name'] ?? ''));
$displayName = $isVendorWorkspace && $vendorName !== '' ? $vendorName : $username;
$identityLabel = $isVendorWorkspace ? 'Vendor workspace' : ($isAdmin ? 'Administrator' : 'Client account');

$loginUrl = '/cnrbyte/signup_login/signup_login.pge.php';
$marketplaceUrl = '/cnrbyte/marketplace/dashboard.pge.php';
if (!$isLoggedIn) {
    $dashboardUrl = $loginUrl;
    $dashboardLabel = 'Sign in to your account';
} elseif ($isAdmin) {
    $dashboardUrl = '/cnrbyte/admin/dashboard.pge.php';
    $dashboardLabel = 'Open admin dashboard';
} elseif ($isVendorWorkspace) {
    $dashboardUrl = '/cnrbyte/vendors/dashboard.pge.php';
    $dashboardLabel = 'Open vendor dashboard';
} else {
    $dashboardUrl = '/cnrbyte/clients/dashboard.pge.php';
    $dashboardLabel = 'Open client dashboard';
}

$navigation = [
    'Marketplace' => $marketplaceUrl,
];
if ($isLoggedIn) {
    if ($isAdmin) {
        $navigation['Admin'] = '/cnrbyte/admin/dashboard.pge.php';
        $navigation['Marketplace management'] = '/cnrbyte/admin/marketplace_lifecycle.pge.php';
    } elseif ($isVendorWorkspace) {
        $navigation['Vendor dashboard'] = '/cnrbyte/vendors/dashboard.pge.php';
        $navigation['Products'] = '/cnrbyte/marketplace/products/dashboard.pge.php';
        $navigation['Branches'] = '/cnrbyte/vendors/branches/dashboard.pge.php';
        $navigation['Staff & HR'] = '/cnrbyte/vendors/branches/shifts/staff/hr/dashboard.pge.php';
    } else {
        $navigation['Client dashboard'] = '/cnrbyte/clients/dashboard.pge.php';
        $navigation['My orders'] = '/cnrbyte/marketplace/marketplace_orders.php';
        $navigation['Business portal'] = '/cnrbyte/vendors/select.pge.php';
    }
    $navigation['Messages'] = '/cnrbyte/messages/dashboard.pge.php';
    $navigation['Profile'] = $isVendorWorkspace
        ? '/cnrbyte/vendors/profile.pge.php'
        : '/cnrbyte/clients/profile.pge.php';
} else {
    $navigation['For businesses'] = $loginUrl;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CnrByte connects customers and businesses through a marketplace and business management workspace.">
    <meta name="theme-color" content="#102a43">
    <title>CnrByte | Marketplace and Business Workspace</title>
    <link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css">
    <link rel="stylesheet" href="/cnrbyte/css/main.css">
    <style>
        .cnr-home { color:#172b3a; background:#f5f8fc; min-height:100vh; }
        .cnr-home a:focus-visible { outline:3px solid #f5b700; outline-offset:3px; }
        .cnr-home-header { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; padding:16px clamp(16px,4vw,56px); background:#fff; border-bottom:1px solid #dbe4ee; }
        .cnr-home-brand { display:inline-flex; align-items:center; gap:10px; color:#102a43; font-weight:900; font-size:1.5rem; text-decoration:none; letter-spacing:-.04em; }
        .cnr-home-brand-mark { display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:#155eef; color:#fff; font-size:1.05rem; letter-spacing:0; }
        .cnr-home-nav { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .cnr-home-nav a { color:#344054; text-decoration:none; padding:9px 12px; border-radius:9px; font-weight:650; font-size:.94rem; }
        .cnr-home-nav a:hover { background:#eef4ff; color:#155eef; }
        .cnr-home-nav .cnr-home-nav-primary { background:#155eef; color:#fff; }
        .cnr-home-nav .cnr-home-nav-primary:hover { background:#1048c7; color:#fff; }
        .cnr-home-identity { display:flex; align-items:center; flex-wrap:wrap; gap:7px; font-size:.86rem; color:#667085; }
        .cnr-home-identity strong { color:#172b3a; }
        .cnr-home-logout { display:inline; margin:0; }
        .cnr-home-logout button { border:0; background:transparent; color:#155eef; font:inherit; cursor:pointer; text-decoration:underline; padding:2px 4px; }
        .cnr-home-subnav { display:flex; gap:8px; flex-wrap:wrap; padding:10px clamp(16px,4vw,56px); background:#fff; border-bottom:1px solid #e4eaf1; }
        .cnr-home-subnav a { color:#475467; text-decoration:none; font-size:.88rem; padding:7px 10px; border-radius:8px; }
        .cnr-home-subnav a:hover { background:#f2f6fc; color:#155eef; }
        .cnr-home-hero { position:relative; overflow:hidden; padding:clamp(44px,8vw,94px) clamp(20px,7vw,92px); background:linear-gradient(120deg,#102a43 0%,#174b72 58%,#155eef 100%); color:#fff; }
        .cnr-home-hero:after { content:""; position:absolute; width:360px; height:360px; border:1px solid rgba(255,255,255,.14); border-radius:50%; right:-100px; top:-130px; box-shadow:0 0 0 42px rgba(255,255,255,.035),0 0 0 88px rgba(255,255,255,.025); }
        .cnr-home-hero-inner { position:relative; z-index:1; max-width:1160px; margin:0 auto; }
        .cnr-home-eyebrow { display:inline-flex; padding:7px 11px; border:1px solid rgba(255,255,255,.3); border-radius:999px; background:rgba(255,255,255,.08); color:#e5efff; font-size:.82rem; font-weight:700; letter-spacing:.04em; }
        .cnr-home-hero h1 { max-width:780px; margin:20px 0 16px; font-size:clamp(2.3rem,5.5vw,4.7rem); line-height:1.04; letter-spacing:-.055em; }
        .cnr-home-hero p { max-width:690px; color:#e1eaf5; font-size:clamp(1rem,1.5vw,1.18rem); line-height:1.75; margin:0; }
        .cnr-home-actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:28px; }
        .cnr-home-button { display:inline-flex; align-items:center; justify-content:center; min-height:46px; padding:12px 18px; border:1px solid transparent; border-radius:10px; text-decoration:none; font-weight:750; }
        .cnr-home-button-primary { background:#fff; color:#123e66; }
        .cnr-home-button-primary:hover { background:#eaf1fb; }
        .cnr-home-button-ghost { border-color:rgba(255,255,255,.55); color:#fff; background:rgba(255,255,255,.06); }
        .cnr-home-button-ghost:hover { background:rgba(255,255,255,.14); }
        .cnr-home-content { max-width:1200px; margin:0 auto; padding:clamp(28px,5vw,58px) 20px; }
        .cnr-home-section-heading { display:flex; flex-wrap:wrap; align-items:end; justify-content:space-between; gap:12px; margin-bottom:22px; }
        .cnr-home-section-heading h2 { margin:0; color:#102a43; font-size:clamp(1.55rem,3vw,2.15rem); letter-spacing:-.035em; }
        .cnr-home-section-heading p { margin:7px 0 0; color:#667085; line-height:1.6; }
        .cnr-home-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
        .cnr-home-card { padding:22px; background:#fff; border:1px solid #dfe7f0; border-radius:16px; box-shadow:0 8px 26px rgba(16,42,67,.045); }
        .cnr-home-card-icon { display:grid; place-items:center; width:46px; height:46px; border-radius:13px; background:#edf4ff; color:#155eef; font-size:1.25rem; font-weight:900; }
        .cnr-home-card h3 { margin:17px 0 8px; color:#102a43; font-size:1.12rem; }
        .cnr-home-card p { margin:0; color:#667085; line-height:1.65; font-size:.96rem; }
        .cnr-home-card a { display:inline-flex; margin-top:17px; color:#155eef; text-decoration:none; font-weight:750; }
        .cnr-home-card a:hover { text-decoration:underline; }
        .cnr-home-steps { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; counter-reset:cnr-step; }
        .cnr-home-step { padding:18px 0 18px 18px; border-left:3px solid #155eef; }
        .cnr-home-step h3 { margin:0 0 7px; color:#102a43; }
        .cnr-home-step p { margin:0; color:#667085; line-height:1.65; }
        .cnr-home-cta { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:20px; padding:clamp(22px,4vw,38px); border-radius:18px; background:#eaf2ff; border:1px solid #d5e4ff; }
        .cnr-home-cta h2 { margin:0 0 8px; color:#102a43; }
        .cnr-home-cta p { margin:0; color:#526477; line-height:1.6; }
        .cnr-home-cta .cnr-home-button { background:#155eef; color:#fff; }
        .cnr-home-footer { display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px; padding:22px clamp(20px,4vw,56px); border-top:1px solid #dbe4ee; background:#fff; color:#667085; font-size:.88rem; }
        .cnr-home-footer a { color:#475467; text-decoration:none; }
        .cnr-home-footer a:hover { color:#155eef; }
        @media(max-width:820px) { .cnr-home-grid,.cnr-home-steps { grid-template-columns:repeat(2,minmax(0,1fr)); } .cnr-home-nav { width:100%; } }
        @media(max-width:560px) { .cnr-home-grid,.cnr-home-steps { grid-template-columns:minmax(0,1fr); } .cnr-home-nav a { flex:1 1 auto; text-align:center; } .cnr-home-identity { width:100%; } .cnr-home-hero { padding-top:44px; padding-bottom:54px; } }
    </style>
</head>
<body class="cnr-home">
<header class="cnr-home-header">
    <a class="cnr-home-brand" href="/cnrbyte/index.php" aria-label="CnrByte home"><span class="cnr-home-brand-mark" aria-hidden="true">C</span><span>CnrByte</span></a>
    <nav class="cnr-home-nav" aria-label="Main navigation">
        <?php foreach ($navigation as $label => $url): ?>
            <a href="<?= cnr_index_h($url) ?>"><?= cnr_index_h($label) ?></a>
        <?php endforeach; ?>
        <?php if ($isLoggedIn): ?>
            <a class="cnr-home-nav-primary" href="<?= cnr_index_h($dashboardUrl) ?>">Dashboard</a>
        <?php else: ?>
            <a href="<?= cnr_index_h($loginUrl) ?>">Sign in</a>
            <a class="cnr-home-nav-primary" href="<?= cnr_index_h($loginUrl) ?>#signup-title">Create account</a>
        <?php endif; ?>
    </nav>
    <?php if ($isLoggedIn): ?>
        <div class="cnr-home-identity" aria-label="Signed-in identity"><span><?= cnr_index_h($identityLabel) ?>:</span><strong><?= cnr_index_h($displayName) ?></strong><form method="post" action="/cnrbyte/signup_login/logout.run.php" class="cnr-home-logout"><input type="hidden" name="csrf_token" value="<?= cnr_index_h($_SESSION['csrf_token'] ?? '') ?>"><button type="submit">Sign out</button></form></div>
    <?php endif; ?>
</header>
<main>
    <section class="cnr-home-hero">
        <div class="cnr-home-hero-inner">
            <span class="cnr-home-eyebrow">MARKETPLACE · BUSINESS TOOLS · CONNECTIONS</span>
            <h1>One place to discover, manage and grow business.</h1>
            <p>CnrByte brings customers and businesses together through a marketplace, business workspaces, order tools and direct communication.</p>
            <div class="cnr-home-actions">
                <a class="cnr-home-button cnr-home-button-primary" href="<?= cnr_index_h($marketplaceUrl) ?>">Explore marketplace <span aria-hidden="true">&nbsp;→</span></a>
                <a class="cnr-home-button cnr-home-button-ghost" href="<?= cnr_index_h($dashboardUrl) ?>"><?= cnr_index_h($dashboardLabel) ?></a>
            </div>
        </div>
    </section>

    <section class="cnr-home-content" aria-labelledby="cnr-home-platform-title">
        <div class="cnr-home-section-heading">
            <div><h2 id="cnr-home-platform-title">A connected workspace</h2><p>Tools for browsing, serving customers and managing business activity.</p></div>
        </div>
        <div class="cnr-home-grid">
            <article class="cnr-home-card"><div class="cnr-home-card-icon" aria-hidden="true">⌕</div><h3>Marketplace</h3><p>Browse available products and services, explore categories and continue to order-related workflows.</p><a href="<?= cnr_index_h($marketplaceUrl) ?>">Browse marketplace →</a></article>
            <article class="cnr-home-card"><div class="cnr-home-card-icon" aria-hidden="true">▦</div><h3>Business workspace</h3><p>Manage a vendor workspace with access to business profile, branches, shifts, staff, products and settings.</p><a href="<?= cnr_index_h($isLoggedIn ? ($isVendorWorkspace ? '/cnrbyte/vendors/dashboard.pge.php' : '/cnrbyte/vendors/select.pge.php') : $loginUrl) ?>">Business access →</a></article>
            <article class="cnr-home-card"><div class="cnr-home-card-icon" aria-hidden="true">↔</div><h3>Orders and financials</h3><p>Use the available order, banking and financial modules appropriate to your account and permissions.</p><a href="<?= cnr_index_h($isLoggedIn ? '/cnrbyte/financial/dashboard.pge.php' : $loginUrl) ?>">Open financial workspace →</a></article>
            <article class="cnr-home-card"><div class="cnr-home-card-icon" aria-hidden="true">✉</div><h3>Messages and notifications</h3><p>Reach the messaging area for customer/vendor conversations and important notifications.</p><a href="<?= cnr_index_h($isLoggedIn ? '/cnrbyte/messages/dashboard.pge.php' : $loginUrl) ?>">Go to messages →</a></article>
            <article class="cnr-home-card"><div class="cnr-home-card-icon" aria-hidden="true">◎</div><h3>Account and profile</h3><p>Keep your account details current and access the profile area associated with your active workspace.</p><a href="<?= cnr_index_h($isLoggedIn ? ($isVendorWorkspace ? '/cnrbyte/vendors/profile.pge.php' : '/cnrbyte/clients/profile.pge.php') : $loginUrl) ?>">Account access →</a></article>
            <article class="cnr-home-card"><div class="cnr-home-card-icon" aria-hidden="true">✓</div><h3>Role-based access</h3><p>Module links are presented according to the current sign-in and active vendor workspace context.</p><a href="<?= cnr_index_h($dashboardUrl) ?>">Continue →</a></article>
        </div>
    </section>

    <section class="cnr-home-content" aria-labelledby="cnr-home-how-title">
        <div class="cnr-home-section-heading"><div><h2 id="cnr-home-how-title">Getting started</h2><p>Start with the area that matches what you need to do.</p></div></div>
        <div class="cnr-home-steps">
            <div class="cnr-home-step"><h3>Explore</h3><p>Open the marketplace and review the categories and listings available to you.</p></div>
            <div class="cnr-home-step"><h3>Sign in or create an account</h3><p>Access account-specific features, order history, messages and profile settings.</p></div>
            <div class="cnr-home-step"><h3>Manage your workspace</h3><p>Use the dashboard and modules available to your account and selected business.</p></div>
        </div>
    </section>

    <section class="cnr-home-content" aria-label="Get started with CnrByte">
        <div class="cnr-home-cta"><div><h2>Ready to continue with CnrByte?</h2><p>Open the marketplace or go to the dashboard for your current account.</p></div><a class="cnr-home-button" href="<?= cnr_index_h($dashboardUrl) ?>"><?= cnr_index_h($dashboardLabel) ?> →</a></div>
    </section>
</main>
<footer class="cnr-home-footer"><span>© <?= date('Y') ?> CnrByte</span><span><a href="/cnrbyte/marketplace/dashboard.pge.php">Marketplace</a> · <a href="/cnrbyte/signup_login/signup_login.pge.php">Account access</a></span></footer>
</body>
</html>
