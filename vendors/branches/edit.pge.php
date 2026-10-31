<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/vendors/vendor_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.shw.php';

unset($_SESSION['current_dashboard'], $_SESSION["selected_pge"]);
$_SESSION['current_dashboard'] = 'branches_dashboard';
$_SESSION["selected_pge"] = "edit_pge";
require_once __DIR__ . '/branches.ui.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Branch</title>

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

    <div class = "hr_edit_body">
        <h2>Edit Branches</h2>
        <?php
            // hierachy
            show_hierarchy($conn);
            show_menu_btns($conn);
        ?>
        <div class="create_products_box">
            <?php
                show_selected_form($conn);
            ?>
        </div>
    </div>
</body>
</html>