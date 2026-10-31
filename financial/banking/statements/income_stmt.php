<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.dbh.php';
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
<title>Income Statement</title>

<link rel="stylesheet" href="/cnrbyte/css/header.css">
<link rel="stylesheet" href="/cnrbyte/css/forms.css">
<link rel="stylesheet" href="/cnrbyte/css/hierarchy.css">
<link rel="stylesheet" href="/cnrbyte/css/menu.css">

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
        <h3 class="form-title">Income Statement</h3>
        <table class="income_stmt_tbl">
            <tr><th>Account</th><th>Amount</th></tr>
            <tr><td>Sales</td><td>R 00</td></tr>
            <tr><td>Cost Of Sales</td><td>R 00</td></tr>
            <tr><td>Gross Profit</td><td>R 00</td></tr>

            <tr><th>Gross Operating Income</th><th>R 00</th></tr>
                <?php //echo //get_list_of_income($conn); ?>

            <tr><th>Gross Operating Profit</th>R 00</th></tr>

            <tr><th>Gross Operating Expenses</th><th>R 00</th></tr>
                <?php //echo get_list_of_expenses($conn); ?>

            <tr><th>Net Operating Profit/Loss before Tax</th><th>R 00</th></tr>
                <tr><td>Finance Costs</td><td>R 00</td></tr>

            <tr><th>Net Profit/Loss before Tax</th><th>R 00</th></tr>
                <tr><td>Taxation</td><td>R 00</td></tr>

            <tr><th>Net Profit/Loss</th><th>R 00</th></tr>
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