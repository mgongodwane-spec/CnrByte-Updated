<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.shw.php';

unset($_SESSION['current_dashboard'], $_SESSION["selected_pge"]);
$_SESSION['current_dashboard'] = 'statements_dashboard';
$_SESSION["selected_pge"] = "create_pge";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bank Statement</title>

<link rel="stylesheet" href="/cnrbyte/css/header.css">
<link rel="stylesheet" href="/cnrbyte/css/forms.css">
<link rel="stylesheet" href="/cnrbyte/css/vendor_profile.css">
<link rel="stylesheet" href="/cnrbyte/css/hierarchy.css">
<link rel="stylesheet" href="/cnrbyte/css/menu.css">
<?php

?>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <div class="stmt_body">
        <form class="signup-form" action="bank_stmt.php">
            <button type="submit">Bank Statement</button>
        </form>
        <form class="signup-form" action="income_stmt.php">
            <button type="submit">Income Statement</button>
        </form>
        <form class="signup-form" action="balance_stmt.php">
            <button type="submit">Balance Statement</button>
        </form>


        <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"];?></h3>
        <h3 class="form-title">Bank Statement</h3>
        <table class="bank_stmt_tbl">
            <tr><th>Date</th><th>Details</th><th>Cash In</th><th>Cash Out</th></tr>
            <tr><td>2025/11/13</td><td>Sold Alluminium(can)</td><td>R 11.20</td><td>R 00</td></td>
        </table>


        <form class="signup-form" action="bank_stmt.php">
            <button type="submit">Bank Statement</button>
        </form>
        <form class="signup-form" action="income_stmt.php">
            <button type="submit">Income Statement</button>
        </form>
        <form class="signup-form" action="balance_stmt.php">
            <button type="submit">Balance Statement</button>
        </form>
    <div>
</body>