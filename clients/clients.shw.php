<?php
declare(strict_types=1);

function create_vendor_form($conn): void {
    if(isset($_SESSION["user_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <div class="login_box">
            <form method="post" action="/cnrbyte/vendors/forms.run.php" enctype="multipart/form-data" class="signup-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <p class="form-title">Create Vendor</p>
                <label>Registration Certificate</label>
                <input class="form-input" type="file" name="reg_cert" required><br>

                <label>Registration Number</label>
                <input class="form-input" type="text" name="reg_no" required><br>

                <label>Enterprise Name</label>
                <input class="form-input" type="text" name="reg_name" required><br>

                <label>Registration Date</label>
                <input class="form-input" type="date" name="reg_date" required><br>

                <label>Business Start Date</label>
                <input class="form-input" type="date" name="starting_date" required><br>

                <label>Business Type</label>
                <select class="form-select" name="business_type">
                    <option value="">-- Select Business type --</option>
                    <?php get_types_by_owners_id($conn, 'business_type'); ?>
                </select>
                <p style="font-size:.9rem;margin:.4rem 0">
                <a href="/cnrbyte/settings/settings.pge.php?type=business_type" class="small-link">Add a new business type</a>
                </p>

                <label>Form of Ownership</label>
                <select name="business_form" class="form-select">
                    <option value="">-- Select Form of Ownership --</option>
                    <option value="Private">Private</option>
                    <option value="Public">Public</option>
                    <option value="Cooperative">Cooperative</option>
                    <option value="Partnership">Partnership</option>
                    <option value="Club">Clubs</option>
                </select><br>

                <label>Operation Status</label>
                <select name="operation_status"  class="form-select">
                    <option value="">--- Select Operation Status ---</option>
                    <option value="In Business">In Business</option>
                    <option value="Deregistered">Deregistered</option>
                </select><br>

                <label>Financial Year End</label>
                <input type="date" name="fin_year_end" class="form-input"><br>

                <label>Tax Number</label>
                <input type="text" name="tax_no" class="form-input"><br>

                <label>Phone</label>
                <input type="tel" name="phone" class="form-input"><br>

                <label>Email</label>
                <input type="email" name="email" class="form-input"><br>

                <button type="submit" name="create_new_vendor" class="form-btn">Submit</button>
            </form><br>
        </div>
        <?php
    }
}
function edit_vendor_logo_form(): void {
    if(isset($_SESSION["vendor_info"], $_SESSION["selected_form"])){
    display_success_error_message();
        ?>
        <div class="login_box">
            <form action="/cnrbyte/vendors/forms.run.php" method="post" class="signup-form" id="logo_form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"]; ?></h3>
                <p class="form-title">Edit Vendor Logo</p>
                <label for="logo_img">Logo Image</label>
                <input type="file" name="logo_img" accept="image/*" required><br>
                <button type="submit" name="insert_edit_profile_logo" class="form-btn">Submit</button>
            </form><br>
        </div>
        <?php
    }
}
function edit_vendor_profile_form(): void {
    if(isset($_SESSION["vendor_info"])){
    display_success_error_message();
        ?>
        <div class="login_box">
            <form action="/cnrbyte/vendors/forms.run.php" method="post" class="signup-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <h3 class="form-title"><?php echo $_SESSION["vendor_info"]["reg_name"]; ?></h3>
                <p class="form-title">Edit Vendor Profile Body</p>

                <label for="heading">Select Heading</label>
                <select name="heading" class="form-input" required>
                    <option value="">--- Select heading ---</option>
                    <option value="about">About</option>
                    <option value="vision">Vision</option>
                    <option value="mission">Mission</option>
                    <option value="values">Values</option>
                </select><br>

                <label for="para_text">Paragraph Text</label>
                <input type="text" name="para_text" class="form-input"></input><br>

                <label for="para_img_file">Image</label>
                <input type="file" name="para_img"><br>

                <button type="submit" name="insert_edit_profile_body" class="form-btn">Submit</button>
            </form><br>
        </div>
        <?php
    }
}
