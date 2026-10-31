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
        <h3 class="form-title">Balance Sheet Statement</h3>
        <table class="balance_stmt_tbl">
            <tr><th>Total Assets</th><th>R 00</th></tr>
                <tr><th>Non-Current Assets</th><th>R 00</th></tr>
                    <tr><th>P.P.E.</th><th>R 00</th></tr>
                        <tr><td>Land</td><td>R 00</td></tr>
                        <tr><td>Buildings</td><td>R 00</td></tr>
                        <tr><td>Machinery</td><td>R 00</td></tr>
                        <tr><td>Vehicles</td><td>R 00</td></tr>
                        <tr><td>Equipment</td><td>R 00</td></tr>

                    <tr><th>Financial Assets</th><th>R 00</th></tr>
                        <tr><td>Fixed Deposit</td><td>R 00</td></tr>
                        <tr><td>Long-term Investments</td><td>R 00</td></tr>

                <tr><th>Current Assets</th><th>R 00</th></tr>
                    <tr><td>Cash & Equivalents</td><td>R 00</td></tr>
                    <tr><td>Inventory on Hand</td><td>R 00</td></tr>
                    <tr><td>Debtors Control</td><td>R 00</td></tr>
                    <tr><td>Financial Assets</td><td>R 00</td></tr>


            <tr><th>Total Equity</th><th>R 00</th></tr>
                <tr><td>Ordinary Capital</td><td>R 00</td></tr>
                <tr><td>Common Stocks</td><td>R 00</td></tr>
                <tr><td>Retained Earnings</td><td>R 00</td></tr>

            <tr><th>Total Liabilities</th><th>R 00</th></tr>
                <tr><th>Non-Current Liabilities</th><th>R 00</th></tr>
                    <tr><td>Mortgages & Bonds</td><td>R 00</td></tr>
                    <tr><td>Other long-term Loans</td><td>R 00</td></tr>

                <tr><th>Current Liabilities</th><th>R 00</th></tr>
                    <tr><td>Creditors Control</td><td>R 00</td></tr>
                    <tr><td>Short-term loans</td><td>R 00</td></tr>
                    <tr><td>Accrued Expenses</td><td>R 00</td></tr>
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