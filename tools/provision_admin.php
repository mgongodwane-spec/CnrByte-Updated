<?php
declare(strict_types=1);

// This utility must only be run from the command line by a trusted server operator.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 2 || trim((string)$argv[1]) === '') {
    fwrite(STDERR, "Usage: php tools/provision_admin.php <existing-username>\n");
    exit(2);
}
require_once __DIR__ . '/../includes/conn.dbh.php';
$username = trim((string)$argv[1]);
$stmt = $conn->prepare('UPDATE clients SET rank=\'admin\' WHERE username=? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$changed = $stmt->affected_rows;
$stmt->close();
if ($changed < 1) {
    fwrite(STDERR, "No account was promoted. Confirm that the username exists and is not already an administrator.\n");
    exit(1);
}
fwrite(STDOUT, "Administrator role assigned to the existing account. Sign out and sign in again to refresh the session.\n");
