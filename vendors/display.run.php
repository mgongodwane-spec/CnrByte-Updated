<?php
declare(strict_types=1);

function create_vendor_form($conn): void {
    if(isset($_SESSION["user_info"], $_SESSION["selected_form"])){
    display_success_error_message();


        ?>
        <div class="login_box">
            <form method="post" action="/cnrbyte/vendors/forms.run.php" enctype="multipart/form-data" class="signup-form">
                <?php echo cnrbyte_csrf_field(); ?>
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
                <?php echo cnrbyte_csrf_field(); ?>
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
                <?php echo cnrbyte_csrf_field(); ?>
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


function show_form_menu_btns(mysqli $conn) {
    if (!isset($_SESSION["selected_pge"], $_SESSION["current_dashboard"])) {
        return;
    }

    $selected_pge = $_SESSION["selected_pge"];
    $current_dashboard = $_SESSION["current_dashboard"];

    if ($current_dashboard === 'vendors_dashboard' && $selected_pge === 'create_pge') {
        $vendorEditSections = [
            'Create Vendor',
            'Create Vendor Organogram'
        ];
        echo "<div class='form-menu-container'>";
            echo "<p class='form-title'>Select Form</p>";
            echo "<form method='post' action='/cnrbyte/includes/select.run.php' class='menu-form'>" . cnrbyte_csrf_field();
            foreach ($vendorEditSections as $section) {
                $safeSection = htmlspecialchars($section, ENT_QUOTES, 'UTF-8');
                echo "<button type='submit' name='selected_form' value='{$safeSection}' class='menu-btn'>{$safeSection}</button>";
            }
            echo "</form>";
        echo "</div>";
        return;
    }

    if ($current_dashboard === 'vendors_dashboard' && $selected_pge === 'edit_pge') {
        $vendorEditSections = [
            'Update Vendor Logo',
            'Update Vendor Profile',
            'Update Vendor Documents',
            'Update Vendor Physical Address',
            'Update Vendor Postal Address',
            'Update Vendor Contacts',
            'Update Vendor Financials'
        ];
        echo "<div class='form-menu-container'>";
            echo "<p class='form-title'>Select Form</p>";
            echo "<form method='post' action='/cnrbyte/includes/select.run.php' class='menu-form'>" . cnrbyte_csrf_field();
            foreach ($vendorEditSections as $section) {
                $safeSection = htmlspecialchars($section, ENT_QUOTES, 'UTF-8');
                echo "<button type='submit' name='selected_form' value='{$safeSection}' class='menu-btn'>{$safeSection}</button>";
            }
            echo "</form>";
        echo "</div>";
        return;
    }
}

function show_selected_vendor_form(mysqli $conn): void
{
    $form = $_SESSION['selected_form'] ?? null;

    $routes = [
        'vendors_dashboard' => [
            'create_pge' => [
                'Create Vendor' => static fn() => create_vendor_form($conn),
                'Create Vendor Organogram' => static fn() => create_organogram_form($conn),
            ],
            'edit_pge' => [
                'Update Vendor Logo' => static fn() => edit_vendor_logo_form(),
                'Update Vendor Profile' => static fn() => edit_vendor_profile_form(),
                'Update Vendor Documents' => static fn() => edit_documents_form($conn),
                'Update Vendor Physical Address' => static fn() => edit_physical_address_form($conn),
                'Update Vendor Postal Address' => static fn() => edit_postal_address_form($conn),
                'Update Vendor Contacts' => static fn() => edit_contacts_form($conn),
                'Update Vendor Financials' => static fn() => edit_financial_form($conn),
            ],
        ],

    ];

    $dashboard = (string)($_SESSION['current_dashboard'] ?? '');
    $page = (string)($_SESSION['selected_pge'] ?? '');
    if (!isset($routes[$dashboard][$page]) || !is_array($routes[$dashboard][$page])) return;
    $available = $routes[$dashboard][$page];
    if ($form === null || !isset($available[$form])) {
        $form = array_key_first($available);
        if ($form === null) return;
        $_SESSION['selected_form'] = $form;
    }
    $available[$form]();
}

function display_success_error_message() {
    if (isset($_SESSION['success_create'])){
        echo "<h3 class='success-title'>Success:</h3>";
        foreach($_SESSION['success_create'] as $sr){
            echo $sr . "<br>";
        }
        unset($_SESSION['success_create']);
    }
    if (isset($_SESSION['errors_create'])){
        echo "<h3 class='error-title'>Errors:</h3>";
        foreach($_SESSION['errors_create'] as $er){
            echo $er . "<br>";
        }
        unset($_SESSION['errors_create']);
    }
}




/**
 * Display organizational structure (ranks) for a vendor
 */
function show_vendors_staff_org_chart_horizontal(mysqli $conn, int $vendor_id): void {
    if ($vendor_id <= 0) {
        return;
    }

    $client_id = $_SESSION["user_info"]["id"] ?? 0;
    if (!$client_id) return;

    $query = "SELECT id, rank, rank_name, occupation, rate FROM ranks WHERE client_id = ? AND vendor_id = ? ORDER BY rank ASC";
    $stmt = $conn->prepare($query);
    if (!$stmt) return;

    $stmt->bind_param("ii", $client_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "<div class='vendor_organogram_section'>";
        echo "<h3>Organizational Structure</h3>";
        echo "<p class='vendor_profile_text'>No organizational structure defined yet.</p>";
        echo "</div>";
        $stmt->close();
        return;
    }

    echo "<div class='vendor_organogram_section'>";
    echo "<h3>Organizational Structure</h3>";
    echo "<div class='organogram_chart'>";

    while ($row = $result->fetch_assoc()) {
        $rank = htmlspecialchars((string)$row['rank'], ENT_QUOTES, 'UTF-8');
        $rank_name = htmlspecialchars((string)$row['rank_name'], ENT_QUOTES, 'UTF-8');
        $occupation = htmlspecialchars((string)$row['occupation'], ENT_QUOTES, 'UTF-8');
        $rate = htmlspecialchars((string)$row['rate'], ENT_QUOTES, 'UTF-8');

        // Create indentation based on rank level
        $indent = intval($rank) * 20;

        echo "<div class='organogram_card' style='margin-left: {$indent}px;'>";
        echo "<div class='org_card_header'>";
        echo "<h4>{$rank_name}</h4>";
        echo "<span class='org_card_level'>Level {$rank}</span>";
        echo "</div>";
        echo "<div class='org_card_body vendor_profile_text'>";
        echo "<p><strong>Occupation:</strong> {$occupation}</p>";
        echo "<p><strong>Rate:</strong> {$rate}</p>";
        echo "</div>";
        echo "</div>";
    }

    echo "</div>";
    echo "</div>";

    $stmt->close();
}

function missing_badge(string $text = 'Not provided'): string {
        return "<span class='missing-badge'>".e($text)."</span>";
    }

function get_vendor_org_ranks_by_vendor_id(mysqli $conn) {

        $query = "SELECT * FROM organogram_ranks WHERE vendor_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $vendor_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row["id"]}' class='btn'>{$row["rank_name"]} :{$row["occupation"]}</option>";
            }
            $stmt->close();
    }