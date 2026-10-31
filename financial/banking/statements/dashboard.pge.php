<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.shw.php';

unset($_SESSION['current_dashboard'], $_SESSION["selected_pge"]);
$_SESSION['current_dashboard'] = 'statements_dashboard';
$_SESSION["selected_pge"] = "dashboard_pge";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statements Dashboard</title>

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

    <div class = "statements_screen_body">
        <h2>Statements Dashboard</h2>
        <form class="signup-form" action="bank_stmt.php">
            <button type="submit">Bank Statement</button>
        </form>
        <form class="signup-form" action="income_stmt.php">
            <button type="submit">Income Statement</button>
        </form>
        <form class="signup-form" action="balance_stmt.php">
            <button type="submit">Balance Statement</button>
        </form>
        <?php
            // hierachy
            show_hierarchy($conn);
            show_menu_btns($conn, $_SESSION["selected_pge"]);
        ?>
    </div>
</body>
</html>