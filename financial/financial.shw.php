<?php
declare(strict_types=1);

function show_financial_accounts_table(mysqli $conn): void
{
    if (!isset($_SESSION['user_info']['id'])) {
        return;
    }
    display_success_error_message();

    $client_id = (int)$_SESSION['user_info']['id'];
    $stmt = $conn->prepare("
        SELECT a.id, a.account_name, a.account_type, a.currency,
               a.opening_balance + IFNULL(SUM(CASE WHEN t.txn_type IN ('deposit', 'income') THEN ABS(t.amount) WHEN t.txn_type IN ('withdrawal', 'expense') THEN -ABS(t.amount) ELSE t.amount END), 0) AS current_balance
        FROM financial_accounts a
        LEFT JOIN financial_transactions t ON t.account_id = a.id
        WHERE a.client_id = ?
        GROUP BY a.id, a.account_name, a.account_type, a.currency, a.opening_balance
        ORDER BY a.id DESC
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();

    echo "<h3 class='form-title'>Financial Accounts</h3>";
    if ($res->num_rows === 0) {
        echo "<p>No financial accounts yet.</p>";
        return;
    }

    echo "<table class='products_tbl'>
            <tr>
                <th>ID</th>
                <th>Account Name</th>
                <th>Type</th>
                <th>Currency</th>
                <th>Balance</th>
            </tr>";
    while ($row = $res->fetch_assoc()) {
        $name = htmlspecialchars((string)$row['account_name'], ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars((string)$row['account_type'], ENT_QUOTES, 'UTF-8');
        $currency = htmlspecialchars((string)$row['currency'], ENT_QUOTES, 'UTF-8');
        $balance = number_format((float)$row['current_balance'], 2);
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$name}</td>
                <td>{$type}</td>
                <td>{$currency}</td>
                <td>{$balance}</td>
            </tr>";
    }
    echo "</table>";
    $stmt->close();
}

function create_financial_account_form(mysqli $conn): void
{
    if (!isset($_SESSION['user_info']['id'])) {
        return;
    }
    display_success_error_message();
    ?>
    <form action="create.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class="form-title">Create Financial Account</h3>

        <label for="account_name">Account Name</label>
        <input type="text" name="account_name" placeholder="Account Name"><br>

        <label for="account_type">Account Type</label>
        <select name="account_type" class="form-input">
            <option value="">--- Select Type ---</option>
            <option value="cash">Cash</option>
            <option value="bank">Bank</option>
            <option value="investment">Investment</option>
            <option value="credit">Credit</option>
        </select><br>

        <label for="currency">Currency</label>
        <input type="text" name="currency" value="ZAR" placeholder="Currency"><br>

        <label for="opening_balance">Opening Balance</label>
        <input type="number" step="0.01" name="opening_balance" placeholder="0.00"><br>

        <button name="create_financial_account" type="submit" class="form-btn">Create Account</button>
    </form>
    <?php
}

function create_financial_transaction_form(mysqli $conn): void
{
    if (!isset($_SESSION['user_info']['id'])) {
        return;
    }
    display_success_error_message();
    $client_id = (int)$_SESSION['user_info']['id'];
    ?>
    <form action="create.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class="form-title">Create Financial Transaction</h3>

        <label for="account_id">Account</label>
        <select name="account_id" class="form-input">
            <option value="">--- Select Account ---</option>
            <?php
            $stmt = $conn->prepare("SELECT id, account_name, account_type FROM financial_accounts WHERE client_id = ? ORDER BY account_name ASC");
            $stmt->bind_param("i", $client_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $label = "{$row['account_name']} ({$row['account_type']})";
                echo "<option value='{$row['id']}'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
            }
            $stmt->close();
            ?>
        </select><br>

        <label for="txn_type">Transaction Type</label>
        <select name="txn_type" class="form-input">
            <option value="">--- Select Type ---</option>
            <option value="deposit">Deposit</option>
            <option value="withdrawal">Withdrawal</option>
            <option value="expense">Expense</option>
            <option value="income">Income</option>
        </select><br>

        <label for="amount">Amount</label>
        <input type="number" step="0.01" name="amount" placeholder="0.00"><br>

        <label for="description">Description</label>
        <input type="text" name="description" placeholder="Description"><br>

        <button name="create_financial_transaction" type="submit" class="form-btn">Save Transaction</button>
    </form>
    <?php
}

function edit_financial_account_form(mysqli $conn): void
{
    if (!isset($_SESSION['user_info']['id'])) {
        return;
    }
    display_success_error_message();
    $client_id = (int)$_SESSION['user_info']['id'];
    ?>
    <form action="edit.run.php" method="post" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class="form-title">Edit Financial Account</h3>

        <label for="account_id">Account</label>
        <select name="account_id" class="form-input">
            <option value="">--- Select Account ---</option>
            <?php
            $stmt = $conn->prepare("SELECT id, account_name, account_type FROM financial_accounts WHERE client_id = ? ORDER BY account_name ASC");
            $stmt->bind_param("i", $client_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $label = "{$row['account_name']} ({$row['account_type']})";
                echo "<option value='{$row['id']}'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
            }
            $stmt->close();
            ?>
        </select><br>

        <label for="account_name">New Account Name</label>
        <input type="text" name="account_name" placeholder="New account name"><br>

        <label for="account_type">New Account Type</label>
        <select name="account_type" class="form-input">
            <option value="">--- Keep Existing ---</option>
            <option value="cash">Cash</option>
            <option value="bank">Bank</option>
            <option value="investment">Investment</option>
            <option value="credit">Credit</option>
        </select><br>

        <button name="edit_financial_account" type="submit" class="form-btn">Update Account</button>
    </form>
    <?php
}
