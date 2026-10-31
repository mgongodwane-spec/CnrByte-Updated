<?php
declare(strict_types=1);
function get_vendor_roster(mysqli $conn, int $vendorId, int $limit=100): array {
    $limit=max(1,min(500,$limit));
    $stmt=$conn->prepare('SELECT r.*,s.first_names,s.last_name,b.branch_name FROM roster r LEFT JOIN staff s ON s.id=r.staff_id AND s.vendor_id=r.vendor_id LEFT JOIN branches b ON b.id=r.branch_id AND b.vendor_id=r.vendor_id WHERE r.vendor_id=? ORDER BY r.day_date DESC LIMIT ?');
    $stmt->bind_param('ii',$vendorId,$limit);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
function get_vendor_payroll_summary(mysqli $conn, int $vendorId): array {
    $stmt=$conn->prepare("SELECT COUNT(*) AS entries, COALESCE(SUM(amount),0) AS net_amount, COALESCE(SUM(earnings),0) AS earnings, COALESCE(SUM(deductions),0) AS deductions FROM staff_payslip WHERE vendor_id=?");
    $stmt->bind_param('i',$vendorId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();return $row;
}
