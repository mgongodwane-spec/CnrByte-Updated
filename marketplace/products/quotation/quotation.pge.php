<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

$clientId = (int)($_SESSION['user_info']['id'] ?? 0);
$rank = cnrbyte_auth_normalize_rank($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? 'client');
if ($clientId < 1 || $rank !== 'client') {
    http_response_code(403);
    exit('A signed-in client account is required to create a quotation.');
}
require_once __DIR__ . '/quotation.shw.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quotation | CnrByte</title>
    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/vendor_profile.css">
    <link rel="stylesheet" href="/cnrbyte/css/hierarchy.css">
    <link rel="stylesheet" href="/cnrbyte/css/menu.css">
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>
<main>
    <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
        <p class="success-title" role="status">Quotation saved successfully.</p>
    <?php elseif (isset($_GET['error'])): ?>
        <p class="error-title" role="alert">The quotation could not be saved. Please check the details and try again.</p>
    <?php endif; ?>
    <?php show_quotation_builder_form($conn); ?>
    <div style="text-align:center;margin-top:20px;">
        <a href="/cnrbyte/marketplace/dashboard.pge.php" class="form-btn" style="text-decoration:none;">Back to Marketplace</a>
    </div>
</main>
</body>
</html>
