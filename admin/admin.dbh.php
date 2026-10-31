<?php
declare(strict_types=1);

/**
 * Get all pending vendors
 */
function get_pending_vendors(object $conn): array {
    $sql = "SELECT v.id AS vendor_id, v.reg_name, v.status, c.id AS user_id, c.username, c.email
            FROM vendors v
            JOIN clients c ON v.user_id = c.id
            WHERE v.status = 'pending'";
    $result = mysqli_query($conn, $sql);
    $vendors = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $vendors[] = $row;
        }
    }
    return $vendors;
}

/**
 * Approve a single vendor
 */
function approve_vendor(object $conn, int $vendor_id, int $admin_id, string $admin_name): bool {
    if ($vendor_id < 1 || $admin_id < 1) return false;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id, COALESCE(user_id, client_id) AS owner_id FROM vendors WHERE id = ? AND status = 'pending' LIMIT 1 FOR UPDATE");
        $stmt->bind_param('i', $vendor_id);
        $stmt->execute();
        $vendor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$vendor || (int)$vendor['owner_id'] < 1) {
            $conn->rollback();
            return false;
        }
        $user_id = (int)$vendor['owner_id'];

        // Promote the account only if it is not an administrator account.
        $stmt = $conn->prepare("UPDATE clients SET rank = 'vendor' WHERE id = ? AND LOWER(rank) NOT IN ('admin','administrator')");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();

        // Conditional update protects against a second administrator approving concurrently.
        $stmt = $conn->prepare("UPDATE vendors SET status = 'approved', verification_status = 'verified' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('i', $vendor_id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed !== 1) {
            $conn->rollback();
            return false;
        }

        $stmt = $conn->prepare('SELECT username FROM clients WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $username = (string)($account['username'] ?? 'Vendor');

        $message = 'Congratulations! Your vendor registration has been approved.';
        $status = 'unread';
        $stmt = $conn->prepare('INSERT INTO messages (sender_id, sender_name, receiver_id, receiver_name, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('isisss', $admin_id, $admin_name, $user_id, $username, $message, $status);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Legacy compatibility wrapper. This historical function actually approved vendors,
 * not products. Delegate to the single guarded implementation rather than retaining
 * a second approval path with weaker transaction and authorization checks.
 */
function approve_product_for_market_place(object $conn, int $vendor_id, int $admin_id, string $admin_name): bool {
    $rank = strtolower((string)($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? $_SESSION['rank'] ?? ''));
    if (!in_array($rank, ['admin', 'administrator'], true) || $admin_id < 1) {
        return false;
    }
    return approve_vendor($conn, $vendor_id, $admin_id, $admin_name);
}

function insert_income_expense(object $conn, string $type, string $type_name){
    $query = "INSERT INTO income_expense_list (type, type_name) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) return;

    $stmt->bind_param('ss', $type, $type_name);
    $stmt->execute();
    $stmt->close();
}

function sql_ins_tbl_col_1(
    object $conn,
    string $table,
    string $col_1,
    string $value_1
){
    $query = "INSERT INTO `" . $table . "` (`" . $col_1 . "`) VALUES (?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) return;

    $stmt->bind_param('s', $value_1);
    $stmt->execute();
    $stmt->close();
}
function sql_ins_tbl_col_2(
    object $conn,
    string $table,
    string $col_1,
    string $value_1,
    string $col_2,
    string $value_2
){
    $query = "INSERT INTO `" . $table . "` (`" . $col_1 . "`, `" . $col_2 . "`) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) return;

    $stmt->bind_param('ss', $value_1, $value_2);
    $stmt->execute();
    $stmt->close();
}
function sql_ins_tbl_col_3(
    object $conn,
    string $table,
    string $col_1,
    string $value_1,
    string $col_2,
    string $value_2,
    string $col_3,
    string $value_3

){
    $query = "INSERT INTO `" . $table . "` (`" . $col_1 . "`, `" . $col_2 . "`, `" . $col_3 . "`) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) return;

    $stmt->bind_param('sss', $value_1, $value_2, $value_3);
    $stmt->execute();
    $stmt->close();
}
function sql_ins_tbl_col_4(
    object $conn,
    string $table,
    string $col_1,
    string $value_1,
    string $col_2,
    string $value_2,
    string $col_3,
    string $value_3,
    string $col_4,
    string $value_4
){
    $query = "INSERT INTO `" . $table . "` (`" . $col_1 . "`, `" . $col_2 . "`, `" . $col_3 . "`, `" . $col_4 . "`) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) return;

    $stmt->bind_param('ssss', $value_1, $value_2, $value_3, $value_4);
    $stmt->execute();
    $stmt->close();
}
