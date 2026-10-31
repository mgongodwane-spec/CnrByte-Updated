<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.shw.php';

unset($_SESSION['current_dashboard'], $_SESSION["selected_pge"]);
$_SESSION['current_dashboard'] = 'shifts_dashboard';
$_SESSION["selected_pge"] = "dashboard_pge";
require_once __DIR__ . '/shifts.ui.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shifts Dashboard</title>

<link rel="stylesheet" href="/cnrbyte/css/header.css">
<link rel="stylesheet" href="/cnrbyte/css/forms.css">
<link rel="stylesheet" href="/cnrbyte/css/vendor_profile.css">
<link rel="stylesheet" href="/cnrbyte/css/hierarchy.css">
<link rel="stylesheet" href="/cnrbyte/css/menu.css">
<?php

?>
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <div class="edit_body">
        <h2>Shifts Dashboard</h2>
        <?php
            show_hierarchy($conn);
            require_once __DIR__ . '/shifts.tbls.php';
            $vendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
            if ($vendorId > 0) {
                $rows = get_vendor_shifts($conn, $vendorId);
                echo '<section class="cnr-form-card"><h3>Shifts by branch</h3>';
                if (!$rows) echo '<p class="cnr-empty-state">No shifts have been created yet.</p>';
                else {
                    echo '<div class="cnr-table-wrap"><table><thead><tr><th>Branch</th><th>Shift</th><th>Created</th></tr></thead><tbody>';
                    foreach ($rows as $row) echo '<tr><td>'.htmlspecialchars($row['branch_name'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($row['shift_name'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($row['created_at'],ENT_QUOTES,'UTF-8').'</td></tr>';
                    echo '</tbody></table></div>';
                }
                echo '<p><a class="cnr-button" href="create.pge.php">Create shift</a> <a class="cnr-button cnr-button-secondary" href="edit.pge.php">Manage shifts</a></p></section>';
            } else echo '<p class="cnr-empty-state">Select a vendor workspace to manage shifts.</p>';
        ?>
    </div>
</body>
</html>
