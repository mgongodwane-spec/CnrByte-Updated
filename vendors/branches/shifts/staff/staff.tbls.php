<?php
declare(strict_types=1);
function get_vendor_staff(mysqli $conn, int $vendorId): array {
    $stmt=$conn->prepare('SELECT s.id,s.branch_id,s.shift_id,s.emp_code,s.first_names,s.last_name,s.rank,s.occupation,s.contract,s.rate,s.start_date,b.branch_name,sh.shift_name FROM staff s LEFT JOIN branches b ON b.id=s.branch_id AND b.vendor_id=s.vendor_id LEFT JOIN shifts sh ON sh.id=s.shift_id AND sh.vendor_id=s.vendor_id WHERE s.vendor_id=? ORDER BY s.last_name,s.first_names');
    $stmt->bind_param('i',$vendorId);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
