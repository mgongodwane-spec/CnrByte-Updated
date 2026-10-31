<?php
// C:/xampp/htdocs/cnrbyte/marketplace/products/qr_code/generate_logic.php
require_once __DIR__ . "/phpqrcode/qrlib.php";

/**
 * Generates a QR code for a specific product and updates the database.
 * Optimized for XAMPP Localhost.
 */
function generateProductQR(int $productId, mysqli $conn): string {
    if ($productId <= 0) {
        throw new InvalidArgumentException('A valid product ID is required.');
    }
    $productCheck = $conn->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    if (!$productCheck) throw new RuntimeException('Unable to prepare product lookup for QR generation.');
    $productCheck->bind_param('i', $productId);
    if (!$productCheck->execute() || !$productCheck->get_result()->fetch_assoc()) {
        $productCheck->close();
        throw new RuntimeException('The requested product does not exist.');
    }
    $productCheck->close();

    // 1. Setup Filename and Directory
    $qr_filename = "qr_" . $productId . ".png";

    // Using $_SERVER['DOCUMENT_ROOT'] ensures it works on XAMPP (C:/xampp/htdocs)
    $qr_save_dir = $_SERVER['DOCUMENT_ROOT'] . "/cnrbyte/uploads/products/qr_codes/";
    $qr_save_path = $qr_save_dir . $qr_filename;

    // 2. Ensure Folder Exists
    if (!is_dir($qr_save_dir) && !mkdir($qr_save_dir, 0750, true) && !is_dir($qr_save_dir)) {
        throw new RuntimeException('QR code storage directory is unavailable.');
    }

    // 3. Build the Scan Link (URL)
    // On XAMPP, this will result in: http://localhost/cnrbyte/marketplace/products/qr_code/track_parcel.php?id=...
    $configuredBaseUrl = trim((string)(getenv('CNRBYTE_BASE_URL') ?: ''));
    if ($configuredBaseUrl !== '') {
        $baseParts = parse_url($configuredBaseUrl);
        if (!is_array($baseParts) || !in_array(strtolower((string)($baseParts['scheme'] ?? '')), ['http', 'https'], true) || empty($baseParts['host']) || isset($baseParts['user']) || isset($baseParts['pass'])) {
            throw new RuntimeException('CNRBYTE_BASE_URL must be a valid http(s) base URL without credentials.');
        }
        $baseUrl = rtrim($configuredBaseUrl, '/');
    } else {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '' || !preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
            throw new RuntimeException('The request host is invalid; configure CNRBYTE_BASE_URL for QR generation.');
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $baseUrl = $protocol . $host;
    }
    $qr_link = $baseUrl . "/cnrbyte/marketplace/products/qr_code/track_parcel.php?id=" . $productId;

    // 4. Generate the Physical Image File
    // QR_ECLEVEL_L is standard, 10 is the size (pixel density)
    try {
        QRcode::png($qr_link, $qr_save_path, QR_ECLEVEL_L, 10);
    } catch (Exception $e) {
        throw new Exception("Failed to generate QR code: " . $e->getMessage());
    }

    // 5. Verify file was created
    if (!file_exists($qr_save_path)) {
        throw new Exception("QR code file was not created at: $qr_save_path");
    }

    // 6. Update Database Column
    $stmt = $conn->prepare("UPDATE products SET qr_code_img = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $stmt->bind_param("si", $qr_filename, $productId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update database: " . $stmt->error);
    }
    $stmt->close();

    return $qr_filename;
}
