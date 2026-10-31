<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
require_once __DIR__ . '/../includes/conn.dbh.php';
if (empty($_SESSION['user_info']['id'])) {
    header('Location: /cnrbyte/signup_login/signup_login.pge.php');
    exit;
}
$clientId = (int)$_SESSION['user_info']['id'];
$vendors = [];
$stmt = $conn->prepare('SELECT id, reg_name, status FROM vendors WHERE (client_id=? OR user_id=?) ORDER BY reg_name');
$stmt->bind_param('ii', $clientId, $clientId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $vendors[] = $row;
$stmt->close();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Select a business — CnrByte</title><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"><link rel="stylesheet" href="/cnrbyte/css/forms.css"></head><body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<main class="cnr-page-container"><section class="cnr-form-card"><h1>Select a business</h1><p>Choose the vendor workspace you want to open. Only businesses associated with your account are shown.</p>
<?php if (!$vendors): ?><p>No vendor accounts were found. <a href="/cnrbyte/clients/dashboard.pge.php">Continue to your client dashboard</a>.</p><?php else: ?>
<?php foreach ($vendors as $vendor): ?><form method="post" action="/cnrbyte/vendors/select.run.php" class="cnr-select-vendor-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="vendor_id" value="<?= (int)$vendor['id'] ?>"><button class="form-btn" type="submit"><?= htmlspecialchars((string)$vendor['reg_name'], ENT_QUOTES, 'UTF-8') ?><?= !empty($vendor['status']) ? ' — '.htmlspecialchars(ucfirst((string)$vendor['status']), ENT_QUOTES, 'UTF-8') : '' ?></button></form><?php endforeach; ?>
<?php endif; ?></section></main></body></html>
