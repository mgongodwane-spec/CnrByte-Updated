<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
$rank = strtolower((string)($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? ''));
if (empty($_SESSION['user_info']['id']) || !in_array($rank, ['admin', 'administrator'], true)) {
    http_response_code(403);
    exit('Administrator access required.');
}
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit('Method not allowed.'); }
function qr_sync_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$generated = 0;
$errors = 0;
$processed = 0;
$hasMore = false;
if ($method === 'POST') {
    cnrbyte_auth_require_post_csrf();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
    require_once __DIR__ . '/generate_logic.php';
    $stmt = $conn->prepare("SELECT id, product_name, qr_code_img FROM products ORDER BY id ASC LIMIT 501");
    if (!$stmt || !$stmt->execute()) { error_log('CnrByte QR sync query failed: ' . $conn->error); http_response_code(500); exit('QR sync could not start. Check the server log.'); }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $processed++;
        if ($processed > 500) { $hasMore = true; break; }
        $id = (int)$row['id'];
        $image = (string)($row['qr_code_img'] ?? '');
        $path = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/cnrbyte/uploads/products/qr_codes/qr_' . $id . '.png';
        if ($image === 'no_qr.png' || $image === '' || !is_file($path)) {
            try { generateProductQR($id, $conn); $generated++; }
            catch (Throwable $e) { error_log('CnrByte QR sync failed for product ' . $id . ': ' . $e->getMessage()); $errors++; }
        }
    }
    $stmt->close();
    $conn->close();
    $processed = min($processed, 500);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CnrByte | QR code maintenance</title><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"><link rel="stylesheet" href="/cnrbyte/css/forms.css"></head><body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>
<main class="cnr-form-card"><h1>QR code maintenance</h1><p>This administrator utility checks product QR files and regenerates missing codes. It does not run from a simple page visit.</p>
<?php if ($method === 'POST'): ?><p role="status">Processed up to <?= (int)$processed ?> products. Generated/repaired: <?= (int)$generated ?>. Errors: <?= (int)$errors ?>.</p><?php if ($hasMore): ?><form method="post" action="/cnrbyte/marketplace/products/qr_code/sync_qrcodes.php"><input type="hidden" name="csrf_token" value="<?= qr_sync_h($_SESSION['csrf_token'] ?? '') ?>"><button class="form-btn" type="submit">Continue sync</button></form><?php endif; ?><?php else: ?><form method="post" action="/cnrbyte/marketplace/products/qr_code/sync_qrcodes.php"><input type="hidden" name="csrf_token" value="<?= qr_sync_h($_SESSION['csrf_token'] ?? '') ?>"><button class="form-btn" type="submit">Start QR sync</button></form><?php endif; ?>
<p><a class="form-btn" href="/cnrbyte/settings/settings.pge.php">Return to Settings</a></p></main></body></html>
