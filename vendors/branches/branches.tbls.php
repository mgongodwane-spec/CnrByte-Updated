<?php
declare(strict_types=1);
function get_vendor_branches(mysqli $conn, int $vendorId): array {
    $stmt=$conn->prepare('SELECT id,branch_name,branch_code,phone,email,created_at FROM branches WHERE vendor_id=? ORDER BY branch_name');
    $stmt->bind_param('i',$vendorId);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
