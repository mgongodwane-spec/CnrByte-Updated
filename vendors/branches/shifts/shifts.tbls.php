<?php
declare(strict_types=1);
function get_vendor_shifts(mysqli $conn, int $vendorId): array {
    $stmt=$conn->prepare('SELECT s.id,s.branch_id,s.shift_name,s.created_at,b.branch_name FROM shifts s JOIN branches b ON b.id=s.branch_id AND b.vendor_id=s.vendor_id WHERE s.vendor_id=? ORDER BY b.branch_name,s.shift_name');
    $stmt->bind_param('i',$vendorId);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
