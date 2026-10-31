<?php
declare(strict_types=1);

//income expense
function create_income_or_expense_form($conn): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["branch_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <form action="create.run.php" method="post" class="signup-form" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"]; ?></h3>
            <h3 class="form-title"><?php echo $_SESSION["branch_info"]["branch_name"]; ?></h3>
            <p class="form-title">Income or Expense</p>

            <input type="hidden" name="vendor_id" value="<?php echo $_SESSION["vendor_info"]["id"]; ?>">
            <input type="hidden" name="branch_id" value="<?php echo $_SESSION["branch_info"]["id"]; ?>">

            <label for="account_id">Select Account e.g. Income</label>
            <select name="account_id" class="form-select" required>
                <option value="">--- Select Account ---</option>
                <?php get_income_and_expense_option_list($conn); ?>
            </select><br>

            <label for="amount">Amount</label>
            <input type="number" name="amount" min="0.01" step="0.01" required placeholder="Enter Amount"><br>

            <label for="trans_date">Transaction Date</label>
            <input type="date" name="trans_date" required placeholder="Enter Date"><br>

            <button type="submit" name="create_income_expense" class="form-btn">Submit</button>
        </form><br>
        <?php
    }
}
//assests and liabilties
function create_long_term_asset_form($conn): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["branch_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <form action="create.run.php" method="post" class="signup-form" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"]; ?></h3>
            <h3 class="form-title"><?php echo $_SESSION["branch_info"]["branch_name"]; ?></h3>
            <p class="form-title">Create Fixed Asset</p>

            <input type="hidden" name="vendor_id" value="<?php echo $_SESSION["vendor_info"]["id"]; ?>">
            <input type="hidden" name="branch_id" value="<?php echo $_SESSION["branch_info"]["id"]; ?>">

            <label for="account_id">Select Long-term Asset</label>
            <select name="account_id" class="form-select" required>
                <option value="">--- Select Asset ---</option>
                <?php get_fixed_asset_option_list($conn); ?>
            </select><br>

            <label for="cost">Cost Price</label>
            <input type="number" name="cost" min="0.01" step="0.01" required placeholder="Enter Cost Price"><br>

            <label for="rate">Depreciation/Appreciation Rate</label>
            <input type="number" name="rate" min="-100" max="100" step="0.0001" required placeholder="Enter Depreciation/Appreciation Rate"><br>

            <label for="serial_no">Serial/Account Number</label>
            <input type="text" name="serial_no" maxlength="180" required placeholder="Enter VIN, Serial, Plot/Erf, Ref etc."><br>

            <label for="date_obtained">Date Obtained</label>
            <input type="date" name="date_obtained" required placeholder="Enter Date"><br>

            <label for="life_span">Life Span</label>
            <input type="text" name="life_span" maxlength="100" required placeholder="Enter Life Span"><br>

            <button type="submit" name="create_long_term_bal_stmt" class="form-btn">Submit</button>
        </form><br>
        <?php
    }
}
//investments creditors
function create_long_term_liability_form($conn): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["branch_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <form action="create.run.php" method="post" class="signup-form" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"]; ?></h3>
            <h3 class="form-title"><?php echo $_SESSION["branch_info"]["branch_name"]; ?></h3>
            <p class="form-title">Take Long-term Liability</p>

            <input type="hidden" name="vendor_id" value="<?php echo $_SESSION["vendor_info"]["id"]; ?>">
            <input type="hidden" name="branch_id" value="<?php echo $_SESSION["branch_info"]["id"]; ?>">

            <label for="account_id">Select Long-term Liability</label>
            <select name="account_id" class="form-select" required>
                <option value="">--- Select Liability ---</option>
                <?php get_fixed_liability_option_list($conn); ?>
            </select><br>

            <label for="cost">Principle</label>
            <input type="number" name="cost" min="0.01" step="0.01" required placeholder="Enter Cost/Principal"><br>

            <label for="rate">Interest</label>
            <input type="number" name="rate" min="0" max="100" step="0.0001" required placeholder="Enter Interest Rate"><br>

            <label for="serial_no">Serial/Account Number</label>
            <input type="text" name="serial_no" maxlength="180" required placeholder="Enter account or contract number"><br>

            <label for="date_obtained">Date Obtained</label>
            <input type="date" name="date_obtained" required placeholder="Enter Date"><br>

            <label for="life_span">Time/Period</label>
            <input type="text" name="life_span" maxlength="100" required placeholder="Enter time/period in months, e.g. 6 or 24"><br>

            <button type="submit" name="create_long_term_bal_stmt" class="form-btn">Submit</button>
        </form><br>
        <?php
    }
}
//Equity
function create_equity_form($conn): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["branch_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <form action="create.run.php" method="post" class="signup-form" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"]; ?></h3>
            <h3 class="form-title"><?php echo $_SESSION["branch_info"]["branch_name"]; ?></h3>
            <p class="form-title">Create or Edit Equity</p>

            <input type="hidden" name="vendor_id" value="<?php echo $_SESSION["vendor_info"]["id"]; ?>">
            <input type="hidden" name="branch_id" value="<?php echo $_SESSION["branch_info"]["id"]; ?>">

            <label for="share_type">Type of Share</label>
            <select name="share_type" class="form-select" required>
                <option value="">--- Select Type ---</option>
                <?php get_types_by_owners_id($conn, 'share_type'); ?>
            </select><br>

            <label for="share_action">Action e.g. Issue, Authorise</label>
            <select name="share_action" class="form-select" required>
                <option value="">--- Select Type ---</option>
                <option value="authorise">Authorise</option>
                <option value="issue">Issue</option>
                <option value="pay_dividends">Dividends</option>
            </select><br>

            <label for="num_of_shares">Number of Shares</label>
            <input type="number" name="num_of_shares" min="1" max="1000000000" step="1" required placeholder="Enter Number"><br>

            <label for="par_value">Par Value</label>
            <input type="number" name="par_value" min="0" max="9999999999.99" step="0.01" required placeholder="Enter Price"><br>

            <label for="issue_date">Issue Date</label>
            <input type="date" name="issue_date" required placeholder="Enter Date"><br>

            <label for="maturity_date">Maturity Date</label>
            <input type="date" name="maturity_date" required placeholder="Enter Date"><br>

            <button type="submit" name="create_equity" class="form-btn">Submit</button>
        </form><br>
        <?php
    }
}