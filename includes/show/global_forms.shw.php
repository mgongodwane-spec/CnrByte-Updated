<?php
declare(strict_types=1);

/** Render a single CSRF-protected vendor-approval form for the admin queue. */
if (!function_exists('show_vendor_approval_form')) {
    function show_vendor_approval_form(int $vendorId): void {
        if ($vendorId < 1) return;
        echo '<form method="post" action="/cnrbyte/admin/approve_vendor.run.php" class="cnr-approval-form" onsubmit="return confirm(\'Approve this vendor registration?\');">';
        echo cnrbyte_csrf_field();
        echo '<input type="hidden" name="vendor_id" value="' . $vendorId . '">';
        echo '<button type="submit" class="form-btn">Approve vendor</button></form>';
    }
}

function show_owners_name(){
    if($_SESSION["current_dashboard"] == 'clients_dashboard'){
        echo $_SESSION["user_info"]["username"];
    }
    if($_SESSION["current_dashboard"] == 'vendors_dashboard'){
        echo $_SESSION["vendor_info"]["reg_name"];
    }
    if($_SESSION["current_dashboard"] == 'branches_dashboard'){
        echo $_SESSION["branch_info"]["branch_name"];
    }
    if($_SESSION["current_dashboard"] == 'shifts_dashboard'){
        echo $_SESSION["shift_info"]["shift_name"];
    }
    if($_SESSION["current_dashboard"] == 'staff_dashboard'){
        echo $_SESSION["staff_info"]["first_names"];
    }
}
function edit_financial_form($conn): void {
    display_success_error_message();
    ?>
    <form action="/cnrbyte/includes/show/global_forms.run.php" method="post" class="signup-form" enctype="multipart/form-data">
        <?php echo cnrbyte_csrf_field(); ?>
        <h3 class="form-title"><?php show_owners_name(); ?></h3>
        <p class="form-title">Edit Financial Details</p>

        <label for="bank_name">Bank Name</label>
        <select name="bank_name" class="form-select">
            <option value="">--- Select Bank ---</option>
            <?php get_types_by_owners_id($conn, 'bank_name'); ?>
        </select><br>

        <label for="branch_code">Branch Code</label>
        <input type="text" name="branch_code" placeholder="Branch Code"><br>

        <label for="acc_type">Account Type</label>
        <select name="acc_type" class="form-select">
            <option value="">--- Select Account Type ---</option>
            <?php get_types_by_owners_id($conn, 'acc_type'); ?>
        </select><br>

        <label for="acc_number">Account Number</label>
        <input type="text" name="acc_number"><br>

        <label for="statement_img">Bank Statement</label>
        <input type="file" name="statement_img"><br>

        <button type="submit" name="edit_banking_details" class="form-btn">Update Banking Details</button>
    </form><br>
    <?php
}
function edit_physical_address_form(): void {
    display_success_error_message();
    ?>
    <form action="/cnrbyte/includes/show/global_forms.run.php" method="post" class="signup-form" enctype="multipart/form-data">
        <?php echo cnrbyte_csrf_field(); ?>
        <h3 class="form-title"><?php show_owners_name(); ?></h3>
        <p class="form-title">Edit Physical Address</p>

        <label for="str_address">Street</label>
        <input type="text" name="str_address" placeholder="Street name or Stand Number"><br>

        <label for="phy_suburb">Suburb</label>
        <input type="text" name="phy_suburb" placeholder="Suburb or Section"><br>

        <label for="phy_town">Town</label>
        <input type="text" name="phy_town" placeholder="Town"><br>

        <label for="phy_province">Province</label>
        <input type="text" name="phy_province" placeholder="Province"><br>

        <label for="phy_postal_code">Postal Code</label>
        <input type="text" name="phy_postal_code" placeholder="Postal or Zip Code"><br>

        <label for="phy_address_img">Proof of Address</label>
        <input type="file" name="phy_address_img"><br>

        <button type="submit" name="edit_phy_address" class="form-btn">Update Address Details</button>
    </form><br>
    <?php
}
function edit_postal_address_form(): void {
    display_success_error_message();
    ?>
    <form action="/cnrbyte/includes/show/global_forms.run.php" method="post" class="signup-form" enctype="multipart/form-data">
        <?php echo cnrbyte_csrf_field(); ?>
        <h3 class="form-title"><?php show_owners_name(); ?></h3>
        <p class="form-title">Edit Postal Address</p>

        <label for="po_box">P.O.Box</label>
        <input type="text" name="po_box" placeholder="P.O. Box"><br>

        <label for="pos_suburb">Suburb</label>
        <input type="text" name="pos_suburb" placeholder="Suburb or Section"><br>

        <label for="pos_town">Town</label>
        <input type="text" name="pos_town" placeholder="Town"><br>

        <label for="pos_province">Province</label>
        <input type="text" name="pos_province" placeholder="Province"><br>

        <label for="pos_postal_code">Postal Code</label>
        <input type="text" name="pos_postal_code" placeholder="Postal or Zip Code"><br>

        <button type="submit" name="edit_pos_address" class="form-btn">Update Address Details</button>
    </form><br>
    <?php
}
function edit_contacts_form(): void {
    display_success_error_message();
    ?>
    <form action="/cnrbyte/includes/show/global_forms.run.php" method="post" class="signup-form" enctype="multipart/form-data">
        <?php echo cnrbyte_csrf_field(); ?>
        <h3 class="form-title"><?php show_owners_name(); ?></h3>
        <p class="form-title">Edit Contacts</p>

        <label for="contact_name">Contact Name</label>
        <input type="text" name="contact_name" placeholder="Phone Number, Email"><br>

        <label for="contact_address">Contact Address</label>
        <input type="text" name="contact_address" placeholder="e.g. 075...Email Address"><br>

        <button type="submit" name="edit_contacts" class="form-btn">Update Contact Details</button>
    </form><br>
    <?php
}
function edit_documents_form($conn): void {
    display_success_error_message();
    ?>
    <form action="/cnrbyte/includes/show/global_forms.run.php" method="post" class="signup-form" enctype="multipart/form-data">
        <?php echo cnrbyte_csrf_field(); ?>
        <h3 class="form-title"><?php show_owners_name(); ?></h3>
        <p class="form-title">Official Documents</p>

        <label for="document_name">Select Document</label>
        <select name="document_name" class="form-input">
            <option value="">--- Select Document ---</option>
            <?php
            if($_SESSION["current_dashboard"] == 'vendors_dashboard'):
                get_types_by_owners_id($conn, "document_type");
            endif;
            if($_SESSION["current_dashboard"] == 'staff_dashboard'):
                get_types_by_owners_id($conn, "qualification_type");
            endif;
            ?>
        </select><br>

        <label for="serial_no">Serial Number</label>
        <input type="text" name="serial_no" placeholder="Document Serial Number"><br>

        <label for="document_img">Upload Document</label>
        <input type="file" name="document_img"><br>

        <button type="submit" name="insert_official_doc" class="form-btn">Submit</button>
    </form><br>
    <?php
}

function create_organogram_form($conn): void {
    display_success_error_message();
        ?>
        <div class="login_box">
            <form action="/cnrbyte/includes/show/global_forms.run.php" method="post" class="signup-form">
            <?php echo cnrbyte_csrf_field(); ?>
        <h3 class="form-title"><?php show_owners_name(); ?></h3>
                <p class="form-title">Create Organogram Ranks</p>

                <label>Rank</label>
                <input type="number" name="rank"><br>

                <label>Rank Name</label>
                <input type="text" name="rank_name"><br>

                <label>Occupation</label>
                <select name="occupation" required><option value="">Select occupation</option><?php get_types_by_owners_id($conn, "occupation_type"); ?></select><br>

                <label>Rate</label>
                <input type="float" name="rate"><br>

                <button type="submit" name="create_org_ranks" class="form-btn">Submit</button>
            </form><br>
        </div>
        <?php
}
