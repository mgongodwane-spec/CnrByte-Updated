<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.shw.php';

unset($_SESSION['current_dashboard'], $_SESSION["selected_pge"]);
$_SESSION['current_dashboard'] = 'staff_dashboard';
$_SESSION["selected_pge"] = "dashboard_pge";
require_once __DIR__ . '/staff.ui.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard</title>

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
        <h2>Staff Dashboard</h2>
        <?php
            show_hierarchy($conn);
            require_once __DIR__ . '/staff.tbls.php';
            $vendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
            if ($vendorId > 0) {
                $rows = get_vendor_staff($conn, $vendorId);
                echo '<section class="cnr-form-card"><h3>Staff directory</h3>';
                if (!$rows) echo '<p class="cnr-empty-state">No staff members have been added yet.</p>';
                else {
                    echo '<div class="cnr-table-wrap"><table><thead><tr><th>Employee code</th><th>Name</th><th>Branch</th><th>Shift</th><th>Position</th><th>Contract</th></tr></thead><tbody>';
                    foreach ($rows as $row) echo '<tr><td>'.htmlspecialchars((string)$row['emp_code'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($row['first_names'].' '.$row['last_name'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars((string)$row['branch_name'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars((string)$row['shift_name'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars((string)$row['occupation'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars((string)$row['contract'],ENT_QUOTES,'UTF-8').'</td></tr>';
                    echo '</tbody></table></div>';
                }
                echo '<p><a class="cnr-button" href="create.pge.php">Add staff member</a> <a class="cnr-button cnr-button-secondary" href="edit.pge.php">Manage staff</a></p></section>';
            } else echo '<p class="cnr-empty-state">Select a vendor workspace to manage staff.</p>';
        ?>
    </div>
</body>
</html>
