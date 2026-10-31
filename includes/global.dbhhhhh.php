<?php
//includes/global.dbh.php
declare(strict_types=1);

require_once __DIR__ . '/notifications.php';

if(isset($_SESSION["current_dashboard"])):
if($_SESSION["current_dashboard"] == 'vendors_dashboard'){
    if(isset($_SESSION["vendor_info"])):
        $owners_id = $_SESSION["vendor_info"]["id"];
        $column = 'vendor_id';
        $reg_name = $_SESSION["vendor_info"]["reg_name"];
    endif;
}
if($_SESSION["current_dashboard"] == 'branches_dashboard'){
    if(isset($_SESSION["branch_info"])):
        $owners_id = $_SESSION["branch_info"]["id"];
        $column = 'branch_id';
        $reg_name = $_SESSION["branch_info"]["branch_name"];
    endif;
}
if($_SESSION["current_dashboard"] == 'shifts_dashboard'){
    if(isset($_SESSION["shift_info"])):
        $owners_id = $_SESSION["shift_info"]["id"];
        $column = 'shift_id';
        $reg_name = $_SESSION["shift_info"]["shift_name"];
    endif;
}
if($_SESSION["current_dashboard"] == 'staff_dashboard'){
    if(isset($_SESSION["staff_info"])):
        $owners_id = $_SESSION["staff_info"]["id"];
        $column = 'staff_id';
        $reg_name = $_SESSION["staff_info"]["first_names"];
    endif;
}
endif;
//unsorted
    function get_category_id(mysqli $conn, $category_id) {
        $query = "SELECT * FROM categories WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
//
    function get_profile_info_by_owners_id(mysqli $conn, int $owners_id) {

        $query = "SELECT * FROM profiles WHERE vendor_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $owners_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    if (!function_exists('get_types_by_owners_id')) {
        function get_types_by_owners_id(mysqli $conn, string $type): void {
            $client_id = (int)($_SESSION['user_info']['id'] ?? 0);
            if (isset($_SESSION['vendor_info']['id'])) {
                $vendor_id = (int)$_SESSION['vendor_info']['id'];
                $ownerStmt = $conn->prepare("SELECT COALESCE(client_id, user_id) AS owner_id FROM vendors WHERE id = ? AND status = 'approved' LIMIT 1");
                if ($ownerStmt) { $ownerStmt->bind_param('i', $vendor_id); $ownerStmt->execute(); $ownerRow = $ownerStmt->get_result()->fetch_assoc(); $ownerStmt->close(); if ($ownerRow) $client_id = (int)$ownerRow['owner_id']; }
            }
            if ($client_id < 1) return;
            $stmt = $conn->prepare('SELECT type_name FROM types WHERE client_id = ? AND type = ? ORDER BY type_name');
            if (!$stmt) return;
            $stmt->bind_param('is', $client_id, $type); $stmt->execute(); $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) { $name = htmlspecialchars((string)$row['type_name'], ENT_QUOTES, 'UTF-8'); echo '<option value="'.$name.'">'.$name.'</option>'; }
            $stmt->close();
        }
    }
    function get_total_colum_from_table_by_owners_id($conn, string $table, string $whre_colum_id, int $colum_id, string $sum_colum): void {
        if (isset($_SESSION['vendor_info'])) {
        display_success_error_message();
            $stmt = $conn->prepare("SELECT SUM($sum_colum)
            AS total FROM $table WHERE $whre_colum_id = ? ");
            $stmt->bind_param("i", $colum_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $_SESSION["total_colum"] = $row['total'];
                $total = $row['total'];
            }
        }
    }

    function get_contacts_by_owners_id(mysqli $conn){
        $current = (string)($_SESSION['current_dashboard'] ?? '');
        $map = ['vendors_dashboard'=>['vendor_id','vendor_info'], 'branches_dashboard'=>['branch_id','branch_info'], 'shifts_dashboard'=>['shift_id','shift_info'], 'staff_dashboard'=>['staff_id','staff_info']];
        if (!isset($map[$current])) return [];
        [$column,$sessionKey] = $map[$current];
        $ownersId = (int)($_SESSION[$sessionKey]['id'] ?? 0);
        if ($ownersId < 1) return [];
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE {$column} = ? ORDER BY id DESC");
        if (!$stmt) return [];
        $stmt->bind_param('i',$ownersId); $stmt->execute(); $result=$stmt->get_result();
        $rows=$result ? $result->fetch_all(MYSQLI_ASSOC) : []; $stmt->close();
        return $rows;
    }

    function get_account_info(mysqli $conn, string $type_id) {

        $query = "SELECT * FROM types WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("s", $type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    function get_type_info(mysqli $conn, string $type_name) {

        $query = "SELECT * FROM types WHERE type_name = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("s", $type_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
//client
    function get_client_info_by_client_id($conn, string $user_id){
        $query = "SELECT * FROM clients WHERE client_id = ?;";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $client_info = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $client_info;
    }
    function get_client_info_by_username($conn, string $username){
        $query = "SELECT * FROM clients WHERE username = ?;";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_info = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $user_info;
    }
    function create_remote_access_to_client($conn, string $username, string $pwd, string $email){
        $query = "INSERT INTO clients(username, pwd, email) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $options = ["cost" => 12];
        $hashedPwd = password_hash($pwd, PASSWORD_BCRYPT, $options);
        $stmt->bind_param('sss', $username, $hashedPwd, $email);
        $stmt->execute();
        $stmt->close();
    }
//vendor branch shift hierachy
    function get_vendor_info_by_client_id(mysqli $conn, int $client_id) {

        $query = "SELECT * FROM vendors WHERE client_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    function get_vendor_info_by_vendor_id(mysqli $conn, int $vendor_id) {

        $query = "SELECT * FROM vendors WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    function get_branch_info_by_branch_id(mysqli $conn, int $branch_id) {

        $query = "SELECT * FROM branches WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    function get_shift_info_by_shift_id(mysqli $conn, int $shift_id) {

        $query = "SELECT * FROM shifts WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $shift_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    function get_staff_info_by_staff_id(mysqli $conn, int $staff_id) {

        $query = "SELECT * FROM staff WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    /**
     * Fetches all organizational ranks for the current client.
     */
    function get_all_org_ranks(mysqli $conn) {
        if (!isset($_SESSION["user_info"]["id"])) return;

        $client_id = (int)$_SESSION["user_info"]["id"];
        // Ordering by 'rank' (the level) so the CEO/Manager stays at the top
        $query = "SELECT * FROM ranks WHERE client_id = ? ORDER BY rank ASC";
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param("i", $client_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row["id"]}'>{$row["rank_name"]} ({$row["occupation"]}) - Rate: {$row["rate"]}</option>";
            }
            $stmt->close();
        }
    }
    function get_dashboard_org_ranks(mysqli $conn) {

        if($_SESSION["current_dashboard"] == 'vendors_dashboard'){
            $owners_id = $_SESSION["vendor_info"]["id"];
            $column = 'vendor_id';
        }
        if($_SESSION["current_dashboard"] == 'branches_dashboard'){
            $owners_id = $_SESSION["branch_info"]["id"];
            $column = 'branch_id';
        }
        if($_SESSION["current_dashboard"] == 'shifts_dashboard'){
            $owners_id = $_SESSION["shift_info"]["id"];
            $column = 'shift_id';
        }
        if($_SESSION["current_dashboard"] == 'staff_dashboard'){
            $owners_id = $_SESSION["staff_info"]["id"];
            $column = 'staff_id';
        }

        $client_id = $_SESSION["user_info"]["id"];
        $query = "SELECT * FROM ranks WHERE client_id = ? AND $column = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("ss", $client_id, $owners_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row["id"]}' class='btn'>{$row["rank_name"]} :{$row["occupation"]}</option>";
            }
            $stmt->close();
    }
    function get_rank_info(mysqli $conn, string $rank_id) {

        $query = "SELECT * FROM ranks WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("s", $rank_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }

//hr
    function get_rate_for_roster_row($conn, $vendor_id, $client_id, $day_name, $day_type, $time_of_day) {
        $sql = "SELECT rate, rate_description
                FROM hr_rates
                WHERE (vendor_id = ? OR client_id = ?)
                AND day_name = ?
                AND day_type = ?
                AND time_of_day = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $vendor_id, $client_id, $day_name, $day_type, $time_of_day);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return [
            'rate' => $row['rate'] ?? 1.0,
            'rate_description' => $row['rate_description'] ?? "Unspecified"
        ];
    }
    function compute_hours(string $time_in, string $time_out, string $lunch_in = '', string $lunch_out = ''): float {
        if (empty($time_in) || empty($time_out)) return 0.0;
        $fmt = 'H:i:s';

        if (strlen($time_in) <= 5) $time_in .= ':00';
        if (strlen($time_out) <= 5) $time_out .= ':00';
        if ($lunch_in && strlen($lunch_in) <= 5) $lunch_in .= ':00';
        if ($lunch_out && strlen($lunch_out) <= 5) $lunch_out .= ':00';

        $in = DateTime::createFromFormat($fmt, $time_in);
        $out = DateTime::createFromFormat($fmt, $time_out);
        if (!$in || !$out) return 0.0;

        if ($out <= $in) $out->modify('+1 day');

        $worked = $out->getTimestamp() - $in->getTimestamp();

        $lunchSeconds = 0;
        if ($lunch_in && $lunch_out) {
            $li = DateTime::createFromFormat($fmt, $lunch_in);
            $lo = DateTime::createFromFormat($fmt, $lunch_out);
            if ($li && $lo) {
                if ($lo <= $li) $lo->modify('+1 day');
                $lunchSeconds = max(0, $lo->getTimestamp() - $li->getTimestamp());
            }
        }

        return round(max(0, $worked - $lunchSeconds) / 3600, 2);
    }
//messages
    function get_contact_info_by_client_id(mysqli $conn, int $contact_id) {
        $query = "SELECT * FROM contacts WHERE contact_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $contact_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    function load_chat($conn){
        if (!isset($_SESSION['contact_info'])) exit();
        $contactId = $_SESSION['contact_info']['contact_id'];

        $client_id = $_SESSION['user_info']['id'];
        $markRead = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        if ($markRead) {
            $markRead->bind_param("ii", $contactId, $client_id);
            $markRead->execute();
            $markRead->close();
        }
        $stmt = $conn->prepare("
            SELECT sender_id, subject, message, created_at
            FROM messages
            WHERE (sender_id = ? AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        ");
        $stmt->bind_param("iiii", $client_id, $contactId, $contactId, $client_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $type = ((int)$row['sender_id'] === (int)$client_id) ? 'sent' : 'received';
            $class = $type === 'sent' ? 'message sent' : 'message received';
            echo "<div class='" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "'>";
            if (!empty($row['subject'])) {
                echo "<strong>" . htmlspecialchars((string)$row['subject'], ENT_QUOTES, 'UTF-8') . "</strong><br>";
            }
            echo htmlspecialchars((string)$row['message'], ENT_QUOTES, 'UTF-8');
            echo "<div style='font-size:0.8rem;margin-top:6px;color:#6b7280;'>" . htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') . "</div>";
            echo "</div>";
        }
        $stmt->close();
    }
    function send_messages($conn, $receiver, $message, string $subject = 'Direct Message'){
        $sender = $_SESSION['user_info']['id'];
        $message = trim((string)$message);
        $subject = trim($subject);

        if ($receiver <= 0 || $message === '' || (int)$receiver === (int)$sender) {
            $_SESSION["errors_create"]["send_message"] = "Unable to send this message.";
            redirection_logic();
        }

        notifications_send($conn, (int)$sender, (int)$receiver, $subject === '' ? 'Direct Message' : $subject, $message);
        $_SESSION["success_create"]["send_message"] = "Message sent";
        redirection_logic();
    }
    function search_client(mysqli $conn): void {
        if (!isset($_GET['search_by'], $_GET['search_value']) || trim((string)$_GET['search_value']) === '') return;
        $allowedFields=['username','email','phone'];
        $searchBy=(string)$_GET['search_by'];
        if (!in_array($searchBy,$allowedFields,true)) { echo '<p class="error-title">Invalid search field.</p>'; return; }
        $searchValue=trim((string)$_GET['search_value']);
        if (mb_strlen($searchValue)>150) { echo '<p class="error-title">Search text is too long.</p>'; return; }
        $clientId=(int)($_SESSION['user_info']['id'] ?? 0);
        if ($clientId<1) return;
        $stmt=$conn->prepare("SELECT id, username, email, phone FROM clients WHERE $searchBy = ? AND id <> ? ORDER BY username ASC LIMIT 50");
        $stmt->bind_param('si',$searchValue,$clientId); $stmt->execute(); $result=$stmt->get_result();
        if ($result->num_rows===0) { echo '<p>No users found for this search.</p>'; $stmt->close(); return; }
        echo '<div class="cnr-table-wrap"><table><thead><tr><th>Username</th><th>Email</th><th>Cellphone</th><th>Action</th></tr></thead><tbody>';
        while($row=$result->fetch_assoc()) {
            $id=(int)$row['id']; $username=htmlspecialchars((string)$row['username'],ENT_QUOTES,'UTF-8');
            $email=htmlspecialchars((string)$row['email'],ENT_QUOTES,'UTF-8'); $phone=htmlspecialchars((string)$row['phone'],ENT_QUOTES,'UTF-8');
            $csrf=htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''),ENT_QUOTES,'UTF-8');
            echo '<tr><td>'.$username.'</td><td>'.$email.'</td><td>'.$phone.'</td><td><form method="post" action="/cnrbyte/messages/create.run.php">'
                .'<input type="hidden" name="csrf_token" value="'.$csrf.'"><input type="hidden" name="contact_id" value="'.$id.'">'
                .'<button type="submit" name="add_contact" class="cnr-button">Add contact</button></form></td></tr>';
        }
        echo '</tbody></table></div>'; $stmt->close();
    }
    function show_all_contacts(mysqli $conn): void {
        $clientId=(int)($_SESSION['user_info']['id'] ?? 0); if ($clientId<1) return;
        $stmt=$conn->prepare('SELECT c.id AS contact_id,c.username AS contact_username,c.email AS contact_email,c.phone AS contact_phone FROM contacts ct INNER JOIN clients c ON c.id=ct.contact_id WHERE ct.client_id=? ORDER BY c.username');
        $stmt->bind_param('i',$clientId); $stmt->execute(); $result=$stmt->get_result();
        if ($result->num_rows===0) { echo '<p>No saved contacts found.</p>'; $stmt->close(); return; }
        echo '<h3>My Contacts</h3><div class="cnr-table-wrap"><table><thead><tr><th>Username</th><th>Email</th><th>Cellphone</th><th>Chat</th></tr></thead><tbody>';
        while($row=$result->fetch_assoc()) {
            $id=(int)$row['contact_id']; $username=htmlspecialchars((string)$row['contact_username'],ENT_QUOTES,'UTF-8');
            $email=htmlspecialchars((string)$row['contact_email'],ENT_QUOTES,'UTF-8'); $phone=htmlspecialchars((string)$row['contact_phone'],ENT_QUOTES,'UTF-8');
            $csrf=htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''),ENT_QUOTES,'UTF-8');
            echo '<tr><td>'.$username.'</td><td>'.$email.'</td><td>'.$phone.'</td><td><form method="post" action="/cnrbyte/messages/create.run.php">'
                .'<input type="hidden" name="csrf_token" value="'.$csrf.'"><input type="hidden" name="contact_id" value="'.$id.'">'
                .'<button type="submit" name="select_contact" class="cnr-button">Open chat</button></form></td></tr>';
        }
        echo '</tbody></table></div>'; $stmt->close();
    }
    function list_contacts($conn){
        if (!isset($_SESSION['user_info']['id'])) {
            echo json_encode([]);
            exit;
        }
        $client_id = $_SESSION['user_info']['id'];

        $query = "SELECT * FROM contacts
        WHERE client_id = ?
        ORDER BY contact_username ASC
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $contacts = [];
        while ($row = $res->fetch_assoc()) {
            $contacts[] = [
                'contact_id' => $row['contact_id'],
                'contact_username' => $row['contact_username'],
                'contact_phone' => $row['contact_phone'],
                'last_message' => $row['last_message'] ?? ''
            ];

            $csrf = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
            $contactId = (int)$row['contact_id'];
            $contactUsername = htmlspecialchars((string)$row['contact_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo "<form method='post' action='/cnrbyte/includes/select.run.php'>"
                . "<input type='hidden' name='csrf_token' value='" . $csrf . "'>"
                . "<button type='submit' name='selected_contact' value='" . $contactId . "' class='menu-btn'>" . $contactUsername . "</button>"
                . "</form>";
                }
    }
//products
    function get_product_info_by_product_id(mysqli $conn, int $product_id) {
        $query = "SELECT * FROM products WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    function get_cost_item_info_by_cost_item_id(mysqli $conn, int $cost_item_id) {
        $query = "SELECT * FROM cost_items WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $cost_item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $info = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $info ?: null;
    }
    function get_ave_from_relevant_tabe_by_product_id(mysqli $conn, int $product_id, string $price_colum, string $table): ?float {
        $query = "SELECT AVG($price_colum) AS avg_value FROM $table WHERE product_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return $row && $row['avg_value'] !== null ? (float)$row['avg_value'] : null;
    }
    function get_ave_from_relevant_tabe_by_cost_item_id(mysqli $conn, int $cost_item_id, string $price_colum, string $table): ?float {
        $query = "SELECT AVG($price_colum) AS avg_value FROM $table WHERE cost_item_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param('i', $cost_item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return $row && $row['avg_value'] !== null ? (float)$row['avg_value'] : null;
    }
    function get_ave_histogram_by_cost_item_id(mysqli $conn, int $cost_item_id, string $average): ?float {
        $query = "SELECT AVG($average) AS avg_value FROM histograms WHERE cost_item_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param('i', $cost_item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return $row && $row['avg_value'] !== null ? (float)$row['avg_value'] : null;
    }
    function get_ave_histogram_by_product_id(mysqli $conn, int $product_id, string $average): ?float {
        $query = "SELECT AVG($average) AS avg_value FROM histograms WHERE product_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return $row && $row['avg_value'] !== null ? (float)$row['avg_value'] : null;
    }
