<?php
declare(strict_types=1);
require_once __DIR__ . '/hr.tbls.php';
function cnr_pending_leave_count(mysqli $conn, int $vendorId): int {
    $stmt=$conn->prepare("SELECT COUNT(*) AS total FROM staff_leave WHERE vendor_id=? AND leave_status='pending'");$stmt->bind_param('i',$vendorId);$stmt->execute();$n=(int)($stmt->get_result()->fetch_assoc()['total']??0);$stmt->close();return $n;
}
