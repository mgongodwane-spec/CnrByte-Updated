<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/product_hierarchy.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/hierarchy.shw.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/form_menu.shw.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/select.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/selected_form.shw.php';

function display_success_error_message() {
    if (isset($_SESSION['success_create'])){
        echo "<h3 class='success-title'>Success:</h3>";
        foreach($_SESSION['success_create'] as $sr){
            echo htmlspecialchars((string)$sr, ENT_QUOTES, 'UTF-8') . "<br>";
        }
        unset($_SESSION['success_create']);
    }
    if (isset($_SESSION['errors_create'])){
        echo "<h3 class='error-title'>Errors:</h3>";
        foreach($_SESSION['errors_create'] as $er){
            echo htmlspecialchars((string)$er, ENT_QUOTES, 'UTF-8') . "<br>";
        }
        unset($_SESSION['errors_create']);
    }
}

function show_vendor_option_list_by_client_id(mysqli $conn) {
    $client_id = $_SESSION["user_info"]["id"];
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row["id"]}'>{$row["reg_name"]}</option>";
    }

    $stmt->close();
}
function show_branch_option_list_by_vendor_id(mysqli $conn) {
    $vendor_id = $_SESSION["vendor_info"]["id"];
    $stmt = $conn->prepare("SELECT * FROM branches WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row["id"]}'>{$row["branch_name"]}</option>";
    }

    $stmt->close();
}
function show_shift_option_list_by_branch_id(mysqli $conn) {
    $branch_id = $_SESSION["branch_info"]["id"];
    $stmt = $conn->prepare("SELECT * FROM shifts WHERE branch_id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row["id"]}'>{$row["shift_name"]}</option>";
    }

    $stmt->close();
}
function show_staff_option_list_by_vendor_id(mysqli $conn) {
    $vendor_id = $_SESSION["vendor_info"]["id"];
    $stmt = $conn->prepare("SELECT * FROM staff WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row["id"]}'>{$row["first_names"]}</option>";
    }

    $stmt->close();
}
function show_staff_option_list_by_branch_id(mysqli $conn) {
    $branch_id = $_SESSION["branch_info"]["id"];
    $stmt = $conn->prepare("SELECT * FROM staff WHERE branch_id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row["id"]}'>{$row["first_names"]}</option>";
    }

    $stmt->close();
}
function show_staff_option_list_by_shift_id(mysqli $conn) {
    $shift_id = $_SESSION["shift_info"]["id"];
    $stmt = $conn->prepare("SELECT * FROM staff WHERE shift_id = ?");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row["id"]}'>{$row["first_names"]}</option>";
    }

    $stmt->close();
}
function show_product_great_categories_by_vendor_id(mysqli $conn) {
    product_hierarchy_bootstrap($conn);
    foreach (product_hierarchy_get_categories($conn, 'great') as $row) {
        echo "<option value='{$row["id"]}'>{$row["category"]}</option>";
    }
}
function show_product_grand_categories_by_great_id(mysqli $conn) {
    product_hierarchy_bootstrap($conn);
    $parent_id = (int)($_SESSION["selected_great"] ?? 0);
    foreach (product_hierarchy_get_categories($conn, 'grand', $parent_id) as $row) {
        echo "<option value='{$row["id"]}'>{$row["category"]}</option>";
    }
}
function show_product_parent_categories_by_grand_id(mysqli $conn) {
    product_hierarchy_bootstrap($conn);
    $parent_id = (int)($_SESSION["selected_grand"] ?? 0);
    foreach (product_hierarchy_get_categories($conn, 'parent', $parent_id) as $row) {
        echo "<option value='{$row["id"]}'>{$row["category"]}</option>";
    }
}
function show_product_child_categories_by_parent_id(mysqli $conn) {
    product_hierarchy_bootstrap($conn);
    $parent_id = (int)($_SESSION["selected_parent"] ?? 0);
    foreach (product_hierarchy_get_categories($conn, 'child', $parent_id) as $row) {
        echo "<option value='{$row["id"]}'>{$row["category"]}</option>";
    }
}

/**
 * Display organizational structure (ranks) for a vendor
 */
function show_vendors_staff_org_chart_horizontal(mysqli $conn, int $vendor_id): void {
    if ($vendor_id <= 0) {
        return;
    }

    $client_id = $_SESSION["user_info"]["id"] ?? 0;
    if (!$client_id) return;

    $query = "SELECT id, rank, rank_name, occupation, rate FROM ranks WHERE client_id = ? AND vendor_id = ? ORDER BY rank ASC";
    $stmt = $conn->prepare($query);
    if (!$stmt) return;

    $stmt->bind_param("ii", $client_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "<div class='vendor_organogram_section'>";
        echo "<h3>Organizational Structure</h3>";
        echo "<p class='vendor_profile_text'>No organizational structure defined yet.</p>";
        echo "</div>";
        $stmt->close();
        return;
    }

    echo "<div class='vendor_organogram_section'>";
    echo "<h3>Organizational Structure</h3>";
    echo "<div class='organogram_chart'>";

    while ($row = $result->fetch_assoc()) {
        $rank = htmlspecialchars((string)$row['rank'], ENT_QUOTES, 'UTF-8');
        $rank_name = htmlspecialchars((string)$row['rank_name'], ENT_QUOTES, 'UTF-8');
        $occupation = htmlspecialchars((string)$row['occupation'], ENT_QUOTES, 'UTF-8');
        $rate = htmlspecialchars((string)$row['rate'], ENT_QUOTES, 'UTF-8');

        // Create indentation based on rank level
        $indent = intval($rank) * 20;

        echo "<div class='organogram_card' style='margin-left: {$indent}px;'>";
        echo "<div class='org_card_header'>";
        echo "<h4>{$rank_name}</h4>";
        echo "<span class='org_card_level'>Level {$rank}</span>";
        echo "</div>";
        echo "<div class='org_card_body vendor_profile_text'>";
        echo "<p><strong>Occupation:</strong> {$occupation}</p>";
        echo "<p><strong>Rate:</strong> {$rate}</p>";
        echo "</div>";
        echo "</div>";
    }

    echo "</div>";
    echo "</div>";

    $stmt->close();
}

function get_income_and_expense_option_list(mysqli $conn) {
    $client_id = $_SESSION["user_info"]["id"];

    $query = "SELECT * FROM types WHERE client_id = ? AND (type = 'income' OR type = 'expense')";
    $stmt = $conn->prepare($query);
    if (!$stmt) return null;

    $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row["id"]}' class='btn'>{$row["type"]}: {$row["type_name"]}</option>";
        }
        $stmt->close();
}
function get_fixed_asset_option_list(mysqli $conn) {
    $client_id = $_SESSION["user_info"]["id"];

    $query = "SELECT * FROM types WHERE client_id = ? AND (type = 'fixed_asset')";
    $stmt = $conn->prepare($query);
    if (!$stmt) return null;

    $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row["id"]}' class='btn'>{$row["type_name"]}</option>";
        }
    $stmt->close();
}
function get_fixed_liability_option_list(mysqli $conn) {
    $client_id = $_SESSION["user_info"]["id"];

    $query = "SELECT * FROM types WHERE client_id = ? AND (type = 'fixed_liability')";
    $stmt = $conn->prepare($query);
    if (!$stmt) return null;

    $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row["id"]}' class='btn'>{$row["type_name"]}</option>";
        }
    $stmt->close();
}

function run_ppe_depreciation(mysqli $conn, int $vendor_id): void
{
    $assets = $conn->prepare("
        SELECT id, cost, rate
        FROM bal_stmt
        WHERE vendor_id = ?
    ");
    $assets->bind_param('i', $vendor_id);
    $assets->execute();
    $res = $assets->get_result();

    while ($asset = $res->fetch_assoc()) {
        $bal_stmt_id = (int)$asset['id'];
        $cost = (float)$asset['cost'];
        $rate = (float)$asset['rate'];

        if ($cost <= 0 || $rate <= 0) {
            continue;
        }

        $monthly_dep = ($cost * ($rate / 100)) / 12;

        // prevent duplicate depreciation for same month
        $check = $conn->prepare("
            SELECT id FROM ppe_depreciation
            WHERE bal_stmt_id = ?
              AND MONTH(dep_date) = MONTH(CURDATE())
              AND YEAR(dep_date) = YEAR(CURDATE())
        ");
        $check->bind_param('i', $bal_stmt_id);
        $check->execute();

        if ($check->get_result()->fetch_assoc()) {
            continue;
        }

        $insert = $conn->prepare("
            INSERT INTO ppe_depreciation (bal_stmt_id, dep_date, dep_amount)
            VALUES (?, CURDATE(), ?)
        ");
        $insert->bind_param('id', $bal_stmt_id, $monthly_dep);
        $insert->execute();
    }
}

function get_ppe_summary(mysqli $conn, int $vendor_id): array
{
    $sql = "
        SELECT
            b.account_name,
            SUM(b.cost - IFNULL(d.dep_total,0)) AS net_value
        FROM bal_stmt b
        LEFT JOIN (
            SELECT bal_stmt_id, SUM(dep_amount) AS dep_total
            FROM ppe_depreciation
            GROUP BY bal_stmt_id
        ) d ON d.bal_stmt_id = b.id
        WHERE b.vendor_id = ?
        GROUP BY b.account_name
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $vendor_id);
    $stmt->execute();

    $result = [];
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $result[$row['account_name']] = (float)$row['net_value'];
    }

    return $result;
}
$vendor_id = $_SESSION['vendor_info']['vendor_id'] ?? null;
// 2. Only run the functions if the vendor_id actually exists
if ($vendor_id) {
    run_ppe_depreciation($conn, $vendor_id);
    $ppe = get_ppe_summary($conn, $vendor_id);
    } else {
    // Optional: provide a default value so other code doesn't break
    $ppe = [];
}
