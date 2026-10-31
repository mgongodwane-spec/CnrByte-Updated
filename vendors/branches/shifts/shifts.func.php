<?php
declare(strict_types=1);
require_once __DIR__ . '/shifts.tbls.php';
function cnr_shift_count(mysqli $conn, int $vendorId): int {
    $stmt=$conn->prepare('SELECT COUNT(*) AS total FROM shifts WHERE vendor_id=?');$stmt->bind_param('i',$vendorId);$stmt->execute();$n=(int)($stmt->get_result()->fetch_assoc()['total']??0);$stmt->close();return $n;
}
