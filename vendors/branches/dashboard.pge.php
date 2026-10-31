<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.shw.php';

unset($_SESSION['current_dashboard'], $_SESSION["selected_pge"]);
$_SESSION['current_dashboard'] = 'branches_dashboard';
$_SESSION["selected_pge"] = "dashboard_pge";
require_once __DIR__ . '/branches.ui.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Branches Dashboard</title>

<link rel="stylesheet" href="/cnrbyte/css/header.css">
<link rel="stylesheet" href="/cnrbyte/css/forms.css">
<link rel="stylesheet" href="/cnrbyte/css/vendor_profile.css">
<link rel="stylesheet" href="/cnrbyte/css/hierarchy.css">
<link rel="stylesheet" href="/cnrbyte/css/menu.css">
<?php

?></head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <div class = "staff_dashboard_body">
        <h2>Branches Dashboard</h2>
        <?php
            show_hierarchy($conn);
            require_once __DIR__ . '/branches.tbls.php';
            $vendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
            if ($vendorId > 0) {
                $rows = get_vendor_branches($conn, $vendorId);
                echo '<section class="cnr-form-card"><h3>Company branches</h3>';
                if (!$rows) echo '<p class="cnr-empty-state">No branches have been created yet.</p>';
                else {
                    echo '<div class="cnr-table-wrap"><table><thead><tr><th>Name</th><th>Code</th><th>Phone</th><th>Email</th></tr></thead><tbody>';
                    foreach ($rows as $row) echo '<tr><td>'.htmlspecialchars($row['branch_name'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($row['branch_code'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars((string)$row['phone'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars((string)$row['email'],ENT_QUOTES,'UTF-8').'</td></tr>';
                    echo '</tbody></table></div>';
                }
                echo '<p><a class="cnr-button" href="create.pge.php">Create branch</a> <a class="cnr-button cnr-button-secondary" href="edit.pge.php">Manage branches</a></p></section>';
            } else echo '<p class="cnr-empty-state">Select a vendor workspace to manage branches.</p>';
        ?>
    </div>
</body>
</html>