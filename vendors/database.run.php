function get_vendor_info_by_client_id(mysqli $conn, int $client_id) {

        $query = "SELECT * FROM vendors WHERE client_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }

function get_vendor_type_by_client_id(mysqli $conn, int $client_id) {

        $query = "SELECT * FROM vendor_types WHERE client_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
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

function show_hierarchy(mysqli $conn) {
    function show_vendor_btn_list_by_user_id(mysqli $conn, int $user_id) {
        $stmt = $conn->prepare("SELECT id, reg_name FROM vendors WHERE client_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "<div class='hierarchy-item vendor-list'>";
        echo "<h4>Select Vendor</h4>";
        echo "<form method='post' action='/cnrbyte/includes/select.run.php' class='hierarchy-form'>" . cnrbyte_csrf_field();
        while ($row = $result->fetch_assoc()) {
            echo "<button type='submit' name='selected_vendor' value='{$row["id"]}' class='btn'>{$row["reg_name"]}</button>";
        }
        echo "</form>";
        echo "</div>";

        $stmt->close();
    }

    function show_branch_btn_list_by_vendor_id(mysqli $conn, int $vendor_id) {
        $stmt = $conn->prepare("SELECT id, branch_name FROM branches WHERE vendor_id = ?");
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "<div class='hierarchy-item branch-list'>";
        echo "<h4>Select Branch</h4>";
        echo "<form method='post' action='/cnrbyte/includes/select.run.php' class='hierarchy-form'>" . cnrbyte_csrf_field();
        while ($row = $result->fetch_assoc()) {
            echo "<button type='submit' name='selected_branch' value='{$row["id"]}' class='btn'>{$row["branch_name"]}</button>";
        }
        echo "</form>";
        echo "</div>";

        $stmt->close();
    }

    function show_shift_btn_list_by_branch_id(mysqli $conn, int $branch_id) {
        $stmt = $conn->prepare("SELECT id, shift_name FROM shifts WHERE branch_id = ?");
        $stmt->bind_param("i", $branch_id);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "<div class='hierarchy-item shift-list'>";
        echo "<h4>Select Shift</h4>";
        echo "<form method='post' action='/cnrbyte/includes/select.run.php' class='hierarchy-form'>" . cnrbyte_csrf_field();
        while ($row = $result->fetch_assoc()) {
            echo "<button type='submit' name='selected_shift' value='{$row["id"]}' class='btn'>{$row["shift_name"]}</button>";
        }
        echo "</form>";
        echo "</div>";

        $stmt->close();
    }

    function show_staff_btn_list_by_shift_id(mysqli $conn, int $shift_id) {
        $stmt = $conn->prepare("SELECT id, first_names FROM staff WHERE shift_id = ?");
        $stmt->bind_param("i", $shift_id);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "<div class='hierarchy-item staff-list'>";
        echo "<h4>Select Staff</h4>";
        echo "<form method='post' action='/cnrbyte/includes/select.run.php' class='hierarchy-form'>" . cnrbyte_csrf_field();
        while ($row = $result->fetch_assoc()) {
            echo "<button type='submit' name='selected_staff' value='{$row["id"]}' class='btn'>{$row["first_names"]}</button>";
        }
        echo "</form>";
        echo "</div>";

        $stmt->close();
    }
    echo "<div class='hierarchy-wrapper'>";

    if (isset($_SESSION['user_info'])) {
        show_vendor_btn_list_by_user_id($conn, $_SESSION['user_info']["id"]);
        if (isset($_SESSION['vendor_info'])) {
            show_branch_btn_list_by_vendor_id($conn, $_SESSION['vendor_info']["id"]);
            if (isset($_SESSION['branch_info'])) {
                show_shift_btn_list_by_branch_id($conn, $_SESSION['branch_info']["id"]);
                if (isset($_SESSION['shift_info'])) {
                    show_staff_btn_list_by_shift_id($conn, $_SESSION['shift_info']["id"]);
                }
            }
        }
    }
    echo "</div>";
}