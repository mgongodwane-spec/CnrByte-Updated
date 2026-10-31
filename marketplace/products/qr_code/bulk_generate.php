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
function qr_bulk_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$count = 0; $errors = 0; $hasMore = false; $processed = 0;
if ($method === 'POST') {
    cnrbyte_auth_require_post_csrf();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
    require_once __DIR__ . '/generate_logic.php';
    $stmt = $conn->prepare("SELECT id, product_name FROM products WHERE qr_code_img = 'no_qr.png' OR qr_code_img IS NULL ORDER BY id ASC LIMIT 101");
    if (!$stmt || !$stmt->execute()) { error_log('CnrByte bulk QR query failed: ' . $conn->error); http_response_code(500); exit('Bulk QR generation could not start. Check the server log.'); }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $processed++;
        if ($processed > 100) { $hasMore = true; break; }
        try { generateProductQR((int)$row['id'], $conn); $count++; }
        catch (Throwable $e) { error_log('CnrByte bulk QR generation failed for product ' . (int)$row['id'] . ': ' . $e->getMessage()); $errors++; }
    }
    $stmt->close(); $conn->close();
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CnrByte | Bulk QR generation</title><link rel="stylesheet" href="/cnrbyte/css/cnrbyte-modules.css"><link rel="stylesheet" href="/cnrbyte/css/forms.css"></head><body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>
<main class="cnr-form-card"><h1>Bulk QR generation</h1><p>Administrator-only maintenance tool. A batch processes no more than 100 products and must be started with a valid form token.</p>
<?php if ($method === 'POST'): ?><p role="status">Generated: <?= (int)$count ?>. Errors: <?= (int)$errors ?>.</p><?php if ($hasMore): ?><form method="post" action="/cnrbyte/marketplace/products/qr_code/bulk_generate.php"><input type="hidden" name="csrf_token" value="<?= qr_bulk_h($_SESSION['csrf_token'] ?? '') ?>"><button class="form-btn" type="submit">Continue processing</button></form><?php endif; ?><?php else: ?><form method="post" action="/cnrbyte/marketplace/products/qr_code/bulk_generate.php"><input type="hidden" name="csrf_token" value="<?= qr_bulk_h($_SESSION['csrf_token'] ?? '') ?>"><button class="form-btn" type="submit">Start bulk generation</button></form><?php endif; ?>
<p><a class="form-btn" href="/cnrbyte/marketplace/dashboard.pge.php">Return to Marketplace</a></p></main></body></html>
