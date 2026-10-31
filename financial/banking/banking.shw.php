<?php
declare(strict_types=1);

function show_banking_applications_table(mysqli $conn): void
{
    if (!isset($_SESSION['user_info']['id'])) {
        return;
    }
    display_success_error_message();

    $client_id = (int)$_SESSION['user_info']['id'];

    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'banking_applications'");
    if ($table_check->num_rows === 0) {
        echo "<h3 class='form-title'>My Banking Applications</h3>";
        echo "<p style='color: orange;'><strong>Notice:</strong> Banking applications table not found. Please run the database setup SQL.</p>";
        return;
    }

    $stmt = $conn->prepare("
        SELECT id, application_type, applicant_name, account_subtype, savings_plan, status, created_at
        FROM banking_applications
        WHERE client_id = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();

    echo "<h3 class='form-title'>My Banking Applications</h3>";
    if ($res->num_rows === 0) {
        echo "<p>No banking applications yet.</p>";
        return;
    }

    echo "<table class='products_tbl'>
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Applicant</th>
                <th>Subtype</th>
                <th>Plan</th>
                <th>Status</th>
                <th>Date</th>
            </tr>";
    while ($row = $res->fetch_assoc()) {
        $safe_name = htmlspecialchars((string)$row['applicant_name'], ENT_QUOTES, 'UTF-8');
        $safe_type = htmlspecialchars((string)$row['application_type'], ENT_QUOTES, 'UTF-8');
        $safe_subtype = htmlspecialchars((string)($row['account_subtype'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safe_plan = htmlspecialchars((string)($row['savings_plan'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safe_status = htmlspecialchars((string)($row['status'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safe_created = htmlspecialchars((string)($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$safe_type}</td>
                <td>{$safe_name}</td>
                <td>{$safe_subtype}</td>
                <td>{$safe_plan}</td>
                <td>{$safe_status}</td>
                <td>{$safe_created}</td>
            </tr>";
    }
    echo "</table>";
    $stmt->close();
}

function show_trust_applications_table(mysqli $conn): void
{
    if (!isset($_SESSION['user_info']['id'])) {
        return;
    }
    display_success_error_message();

    $client_id = (int)$_SESSION['user_info']['id'];

    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'trust_applications'");
    if ($table_check->num_rows === 0) {
        echo "<h3 class='form-title'>My Trust Applications</h3>";
        echo "<p style='color: orange;'><strong>Notice:</strong> Trust applications table not found. Please run the database setup SQL.</p>";
        return;
    }

    $stmt = $conn->prepare("
        SELECT id, trust_name, trustee_name, trust_type, member_count, beneficiary_count, status, created_at
        FROM trust_applications
        WHERE client_id = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();

    echo "<h3 class='form-title'>My Trust Applications</h3>";
    if ($res->num_rows === 0) {
        echo "<p>No trust applications yet.</p>";
        return;
    }

    echo "<table class='products_tbl'>
            <tr>
                <th>ID</th>
                <th>Trust Name</th>
                <th>Trustee</th>
                <th>Type</th>
                <th>Members</th>
                <th>Beneficiaries</th>
                <th>Status</th>
                <th>Date</th>
            </tr>";
    while ($row = $res->fetch_assoc()) {
        $safe_trust = htmlspecialchars((string)$row['trust_name'], ENT_QUOTES, 'UTF-8');
        $safe_trustee = htmlspecialchars((string)$row['trustee_name'], ENT_QUOTES, 'UTF-8');
        $safe_type = htmlspecialchars((string)($row['trust_type'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safe_status = htmlspecialchars((string)($row['status'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safe_created = htmlspecialchars((string)($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        $member_count = (int)($row['member_count'] ?? 0);
        $beneficiary_count = (int)($row['beneficiary_count'] ?? 0);

        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$safe_trust}</td>
                <td>{$safe_trustee}</td>
                <td>{$safe_type}</td>
                <td>{$member_count}</td>
                <td>{$beneficiary_count}</td>
                <td>{$safe_status}</td>
                <td>{$safe_created}</td>
            </tr>";
    }
    echo "</table>";
    $stmt->close();
}

function create_banking_application_form(mysqli $conn): void
{
    if (!isset($_SESSION['user_info']['id'])) {
        return;
    }
    display_success_error_message();
    ?>
    <form action="create.run.php" method="post" enctype="multipart/form-data" class="signup-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <h3 class="form-title">New Banking Application</h3>

        <label for="application_type">Application Type</label>
        <select name="application_type" id="application_type" class="form-input" onchange="toggleBankingApplicationType(this.value)">
            <option value="">--- Select Type ---</option>
            <option value="personal">Personal</option>
            <option value="business">Business</option>
            <option value="trust">Trust</option>
        </select><br>

        <div id="personal_fields" style="display:none;">
            <label for="first_names">First Names</label>
            <input type="text" name="first_names" placeholder="First Names"><br>

            <label for="surname">Surname</label>
            <input type="text" name="surname" placeholder="Surname"><br>

            <label for="identity_no">Identity Number</label>
            <input type="text" name="identity_no" placeholder="Identity Number"><br>

            <label for="account_subtype">Account Subtype</label>
            <select name="account_subtype" class="form-input" onchange="toggleSavingsPlan(this.value)">
                <option value="">--- Select Subtype ---</option>
                <option value="transactional">Transactional</option>
                <option value="savings">Savings</option>
            </select><br>

            <div id="savings_plan_box" style="display:none;">
                <label for="savings_plan">Savings Plan</label>
                <select name="savings_plan" class="form-input">
                    <option value="">--- Select Plan ---</option>
                    <option value="flexible">Flexible</option>
                    <option value="fixed">Fixed</option>
                </select><br>
            </div>
        </div>

        <div id="business_fields" style="display:none;">
            <label for="business_name">Business Name</label>
            <input type="text" name="business_name" placeholder="Business Name"><br>

            <label for="reg_no">Registration Number</label>
            <input type="text" name="reg_no" placeholder="Registration Number"><br>
        </div>

        <div id="trust_fields" style="display:none;margin-top:8px;">
            <label for="trust_name">Trust Name</label>
            <input type="text" name="trust_name" placeholder="Trust Name"><br>

            <label for="trustee_name">Trustee Name</label>
            <input type="text" name="trustee_name" placeholder="Trustee Name"><br>

            <label for="trust_type">Trust Type</label>
            <select name="trust_type" class="form-input">
                <option value="">--- Select Type ---</option>
                <option value="family">Family</option>
                <option value="charitable">Charitable</option>
                <option value="investment">Investment</option>
                <option value="pension">Pension</option>
                <option value="other">Other</option>
            </select><br>

            <fieldset style="border: 1px solid #ccc; padding: 15px; margin: 15px 0; border-radius: 4px;">
                <legend style="padding: 0 10px;"><strong>Trust Members</strong></legend>
                <p style="font-size: 0.9em; color: #666;">Add members who are part of this trust.</p>

                <div id="members_container">
                    <div class="member_row" style="background:#f9f9f9; padding:12px; margin-bottom:10px; border-radius:4px; border-left:3px solid #007bff;">
                        <button type="button" class="btn-remove-member" onclick="removeMember(this)" style="float:right; background:#dc3545; color:white; padding:4px 8px; border:none; border-radius:3px; cursor:pointer; font-size:0.85em;">Remove</button>

                        <label>Member Name</label>
                        <input type="text" name="member_name[]" placeholder="Member Name"><br>

                        <label>Member Surname</label>
                        <input type="text" name="member_surname[]" placeholder="Member Surname"><br>

                        <label>Identity Number / ID</label>
                        <input type="text" name="member_id_no[]" placeholder="ID Number"><br>

                        <label>Deposit Amount</label>
                        <input type="number" name="member_deposit[]" placeholder="0.00" step="0.01"><br>

                        <fieldset style="border: 1px solid #ddd; padding: 10px; margin-top: 10px; border-radius: 3px;">
                            <legend style="padding: 0 5px;"><strong>Beneficiaries for this Member</strong></legend>
                            <p style="font-size: 0.8em; color: #666;">Add beneficiaries (optional)</p>

                            <div class="beneficiary_container">
                                <div class="beneficiary_row" style="background:white; padding:8px; margin-bottom:8px; border-left:2px solid #28a745;">
                                    <button type="button" class="btn-remove-beneficiary" onclick="removeBeneficiary(this)" style="float:right; background:#dc3545; color:white; padding:3px 6px; border:none; border-radius:3px; cursor:pointer; font-size:0.8em;">Remove</button>

                                    <label style="font-size:0.9em;">Beneficiary Name</label>
                                    <input type="hidden" name="beneficiary_member_index[]" class="beneficiary-member-index" value="0"><input type="text" name="beneficiary_name[]" placeholder="Beneficiary Name" style="width:100%; font-size:0.9em;"><br>

                                    <label style="font-size:0.9em;">Relationship</label>
                                    <input type="text" name="beneficiary_relationship[]" placeholder="e.g., Spouse, Child, Parent" style="width:100%; font-size:0.9em;"><br>

                                    <label style="font-size:0.9em;">Allocation %</label>
                                    <input type="number" name="beneficiary_allocation[]" placeholder="0" min="0" max="100" step="0.01" style="width:100%; font-size:0.9em;">
                                </div>
                            </div>

                            <button type="button" class="btn-add-beneficiary" onclick="addBeneficiary(this)" style="background:#28a745; color:white; padding:6px 12px; border:none; border-radius:3px; cursor:pointer; font-size:0.85em; margin-top:8px;">+ Add Beneficiary</button>
                        </fieldset>
                    </div>
                </div>

                <button type="button" onclick="addMember()" style="background:#007bff; color:white; padding:8px 16px; border:none; border-radius:3px; cursor:pointer; margin-top:10px;">+ Add Member</button>
            </fieldset>
        </div>

        <label for="doc_upload">Upload Supporting Document</label>
        <input type="file" name="doc_upload"><br>

        <button name="create_banking_application" type="submit" class="form-btn">Submit Application</button>
    </form>
    <script>
    let memberCount = 1;

    function addMember() {
        const container = document.getElementById('members_container');
        memberCount++;
        const newMemberRow = document.createElement('div');
        newMemberRow.className = 'member_row';
        newMemberRow.style.cssText = 'background:#f9f9f9; padding:12px; margin-bottom:10px; border-radius:4px; border-left:3px solid #007bff;';
        newMemberRow.innerHTML = `
            <button type="button" class="btn-remove-member" onclick="removeMember(this)" style="float:right; background:#dc3545; color:white; padding:4px 8px; border:none; border-radius:3px; cursor:pointer; font-size:0.85em;">Remove</button>

            <label>Member Name</label>
            <input type="text" name="member_name[]" placeholder="Member Name"><br>

            <label>Member Surname</label>
            <input type="text" name="member_surname[]" placeholder="Member Surname"><br>

            <label>Identity Number / ID</label>
            <input type="text" name="member_id_no[]" placeholder="ID Number"><br>

            <label>Deposit Amount</label>
            <input type="number" name="member_deposit[]" placeholder="0.00" step="0.01"><br>

            <fieldset style="border: 1px solid #ddd; padding: 10px; margin-top: 10px; border-radius: 3px;">
                <legend style="padding: 0 5px;"><strong>Beneficiaries for this Member</strong></legend>
                <p style="font-size: 0.8em; color: #666;">Add beneficiaries (optional)</p>

                <div class="beneficiary_container">
                    <div class="beneficiary_row" style="background:white; padding:8px; margin-bottom:8px; border-left:2px solid #28a745;">
                        <button type="button" class="btn-remove-beneficiary" onclick="removeBeneficiary(this)" style="float:right; background:#dc3545; color:white; padding:3px 6px; border:none; border-radius:3px; cursor:pointer; font-size:0.8em;">Remove</button>

                        <label style="font-size:0.9em;">Beneficiary Name</label>
                        <input type="hidden" name="beneficiary_member_index[]" class="beneficiary-member-index" value="0"><input type="text" name="beneficiary_name[]" placeholder="Beneficiary Name" style="width:100%; font-size:0.9em;"><br>

                        <label style="font-size:0.9em;">Relationship</label>
                        <input type="text" name="beneficiary_relationship[]" placeholder="e.g., Spouse, Child, Parent" style="width:100%; font-size:0.9em;"><br>

                        <label style="font-size:0.9em;">Allocation %</label>
                        <input type="number" name="beneficiary_allocation[]" placeholder="0" min="0" max="100" step="0.01" style="width:100%; font-size:0.9em;">
                    </div>
                </div>

                <button type="button" class="btn-add-beneficiary" onclick="addBeneficiary(this)" style="background:#28a745; color:white; padding:6px 12px; border:none; border-radius:3px; cursor:pointer; font-size:0.85em; margin-top:8px;">+ Add Beneficiary</button>
            </fieldset>
        `;
        container.appendChild(newMemberRow);
    }

    function removeMember(btn) {
        const container = document.getElementById('members_container');
        if (container.querySelectorAll('.member_row').length > 1) {
            btn.closest('.member_row').remove();
        } else {
            alert('You must have at least one member.');
        }
    }

    function addBeneficiary(btn) {
        const beneficiaryContainer = btn.previousElementSibling;
        const newBeneficiaryRow = document.createElement('div');
        newBeneficiaryRow.className = 'beneficiary_row';
        newBeneficiaryRow.style.cssText = 'background:white; padding:8px; margin-bottom:8px; border-left:2px solid #28a745;';
        newBeneficiaryRow.innerHTML = `
            <button type="button" class="btn-remove-beneficiary" onclick="removeBeneficiary(this)" style="float:right; background:#dc3545; color:white; padding:3px 6px; border:none; border-radius:3px; cursor:pointer; font-size:0.8em;">Remove</button>

            <label style="font-size:0.9em;">Beneficiary Name</label>
            <input type="hidden" name="beneficiary_member_index[]" class="beneficiary-member-index" value="0"><input type="text" name="beneficiary_name[]" placeholder="Beneficiary Name" style="width:100%; font-size:0.9em;"><br>

            <label style="font-size:0.9em;">Relationship</label>
            <input type="text" name="beneficiary_relationship[]" placeholder="e.g., Spouse, Child, Parent" style="width:100%; font-size:0.9em;"><br>

            <label style="font-size:0.9em;">Allocation %</label>
            <input type="number" name="beneficiary_allocation[]" placeholder="0" min="0" max="100" step="0.01" style="width:100%; font-size:0.9em;">
        `;
        beneficiaryContainer.appendChild(newBeneficiaryRow);
    }

    function removeBeneficiary(btn) {
        const beneficiaryContainer = btn.closest('.beneficiary_container');
        const rows = beneficiaryContainer.querySelectorAll('.beneficiary_row');
        if (rows.length > 1) {
            btn.closest('.beneficiary_row').remove();
        } else {
            btn.closest('.beneficiary_row').querySelectorAll('input').forEach(input => input.value = '');
        }
    }

    function toggleBankingApplicationType(type) {
        document.getElementById('personal_fields').style.display = type === 'personal' ? 'block' : 'none';
        document.getElementById('business_fields').style.display = type === 'business' ? 'block' : 'none';
        document.getElementById('trust_fields').style.display = type === 'trust' ? 'block' : 'none';
    }
    const bankingApplicationForm = document.querySelector('form[action="create.run.php"]');
    if (bankingApplicationForm) {
        bankingApplicationForm.addEventListener('submit', function () {
            Array.from(document.querySelectorAll('#members_container .member_row')).forEach((memberRow, memberIndex) => {
                memberRow.querySelectorAll('.beneficiary-member-index').forEach(input => { input.value = String(memberIndex); });
            });
        });
    }
    function toggleSavingsPlan(subtype) {
        document.getElementById('savings_plan_box').style.display = subtype === 'savings' ? 'block' : 'none';
    }
    </script>
    <?php
}

function edit_trust_application_form(mysqli $conn): void
{
    if (empty($_SESSION['user_info']['id'])) return;
    $client_id = (int)$_SESSION['user_info']['id'];
    $stmt = $conn->prepare("SELECT id, trust_name, trustee_name, status FROM trust_applications WHERE client_id = ? ORDER BY id DESC");
    $stmt->bind_param('i', $client_id); $stmt->execute(); $rows = $stmt->get_result();
    echo '<section class="signup-form"><h3 class="form-title">Trust Applications</h3><p>Application status is managed by the administrator.</p><ul>';
    while ($row = $rows->fetch_assoc()) echo '<li>#'.(int)$row['id'].' — '.htmlspecialchars((string)$row['trust_name'], ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8').')</li>';
    echo '</ul></section>'; $stmt->close();
}

function edit_banking_application_form(mysqli $conn): void
{
    if (empty($_SESSION['user_info']['id'])) return;
    $client_id = (int)$_SESSION['user_info']['id'];
    $stmt = $conn->prepare("SELECT id, application_type, applicant_name, status FROM banking_applications WHERE client_id = ? ORDER BY id DESC");
    $stmt->bind_param('i', $client_id); $stmt->execute(); $rows = $stmt->get_result();
    echo '<section class="signup-form"><h3 class="form-title">Banking Applications</h3><p>Application status is managed by the administrator.</p><ul>';
    while ($row = $rows->fetch_assoc()) echo '<li>#'.(int)$row['id'].' — '.htmlspecialchars((string)$row['application_type'], ENT_QUOTES, 'UTF-8').' · '.htmlspecialchars((string)$row['applicant_name'], ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8').')</li>';
    echo '</ul></section>'; $stmt->close();
}
