<?php
declare(strict_types=1);
require_once __DIR__ . '/session.run.php';
require_once __DIR__ . '/conn.dbh.php';

if (!function_exists('redirection_logic')) {
    function redirection_logic(?string $url = null): void {
        $target = $url;
        if ($target === null) {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($referer !== '' && $host !== '' && parse_url($referer, PHP_URL_HOST) === $host) {
                $target = $referer;
            } else {
                $target = '/cnrbyte/vendors/dashboard.pge.php';
            }
        }
        header('Location: ' . $target);
        exit;
    }
}
if (!function_exists('cnrbyte_csrf_validate')) {
    function cnrbyte_csrf_validate(): bool {
        $posted = (string)($_POST['csrf_token'] ?? '');
        $session = (string)($_SESSION['csrf_token'] ?? '');
        return $posted !== '' && $session !== '' && hash_equals($session, $posted);
    }
}
if (!function_exists('cnrbyte_csrf_field')) {
    function cnrbyte_csrf_field(): string {
        $token = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
if (!function_exists('get_rank_info')) {
    function get_rank_info(mysqli $conn, int $rankId): array {
        $stmt = $conn->prepare('SELECT * FROM ranks WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $rankId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return $row;
    }
}
if (!function_exists('get_rate_for_roster_row')) {
    function get_rate_for_roster_row(mysqli $conn, int $vendorId, ?int $clientId, string $dayName, string $dayType, string $timeOfDay): array {
        $stmt = $conn->prepare(
            'SELECT rate, rate_description FROM hr_rates WHERE vendor_id = ? AND day_name = ? AND day_type = ? AND time_of_day = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->bind_param('isss', $vendorId, $dayName, $dayType, $timeOfDay);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: ['rate' => 0, 'rate_description' => 'Unspecified'];
        $stmt->close();
        return $row;
    }
}

/**
 * Session-selection endpoint used by legacy hierarchy buttons.
 * Selection is read-only and every entity is checked against the current vendor/client.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($conn) && $conn instanceof mysqli) {
    $selectionKeys = ['selected_vendor','selected_branch','selected_shift','selected_staff','selected_product','selected_cost_item','selected_form','selected_contact'];
    $isSelectionPost = false; foreach ($selectionKeys as $selectionKey) { if (isset($_POST[$selectionKey])) { $isSelectionPost = true; break; } }
    if ($isSelectionPost && !cnrbyte_csrf_validate()) { http_response_code(403); exit('Invalid or expired form token. Refresh the page and try again.'); }
    $redirect = static function (): void {
        $fallback = '/cnrbyte/clients/dashboard.pge.php';
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($ref !== '' && $host !== '' && parse_url($ref, PHP_URL_HOST) === preg_replace('/:\d+$/', '', $host)) {
            $path = (string)(parse_url($ref, PHP_URL_PATH) ?? '');
            if (substr($path, 0, 8) === '/cnrbyte/') {
                $query = (string)(parse_url($ref, PHP_URL_QUERY) ?? '');
                header('Location: '.$path.($query !== '' ? '?'.$query : ''));
                exit;
            }
        }
        header('Location: '.$fallback); exit;
    };
    $clientId = (int)($_SESSION['user_info']['id'] ?? 0);
    $vendorId = (int)($_SESSION['vendor_info']['id'] ?? 0);
    if (isset($_POST['selected_vendor'])) {
        $id = (int)$_POST['selected_vendor'];
        $isAdmin = in_array(strtolower((string)($_SESSION['user_info']['rank'] ?? '')), ['admin','administrator'], true);
        $sql = $isAdmin ? 'SELECT * FROM vendors WHERE id=? LIMIT 1' : 'SELECT * FROM vendors WHERE id=? AND (client_id=? OR user_id=?) LIMIT 1';
        $stmt = $conn->prepare($sql);
        if ($isAdmin) $stmt->bind_param('i',$id); else $stmt->bind_param('iii',$id,$clientId,$clientId);
        $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($row) {
            $_SESSION['vendor_info']=$row;
            unset($_SESSION['branch_info'],$_SESSION['shift_info'],$_SESSION['staff_info']);
            $_SESSION['current_dashboard']='vendors_dashboard';
        } else $_SESSION['errors_create']=['selection'=>'Vendor not found or access denied.'];
        $redirect();
    }
    if (isset($_POST['selected_branch'])) {
        $id=(int)$_POST['selected_branch'];
        $stmt=$conn->prepare('SELECT * FROM branches WHERE id=? AND vendor_id=? LIMIT 1');$stmt->bind_param('ii',$id,$vendorId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($row){$_SESSION['branch_info']=$row;unset($_SESSION['shift_info'],$_SESSION['staff_info']);$_SESSION['current_dashboard']='branches_dashboard';}
        else $_SESSION['errors_create']=['selection'=>'Branch not found or access denied.'];
        $redirect();
    }
    if (isset($_POST['selected_shift'])) {
        $id=(int)$_POST['selected_shift'];$branchId=(int)($_SESSION['branch_info']['id']??0);
        $stmt=$conn->prepare('SELECT * FROM shifts WHERE id=? AND vendor_id=? AND branch_id=? LIMIT 1');$stmt->bind_param('iii',$id,$vendorId,$branchId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($row){$_SESSION['shift_info']=$row;unset($_SESSION['staff_info']);$_SESSION['current_dashboard']='shifts_dashboard';}
        else $_SESSION['errors_create']=['selection'=>'Shift not found or access denied.'];
        $redirect();
    }
    if (isset($_POST['selected_staff'])) {
        $id=(int)$_POST['selected_staff'];
        $stmt=$conn->prepare('SELECT * FROM staff WHERE id=? AND vendor_id=? LIMIT 1');$stmt->bind_param('ii',$id,$vendorId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($row){$_SESSION['staff_info']=$row;$_SESSION['current_dashboard']='staff_dashboard';}
        else $_SESSION['errors_create']=['selection'=>'Staff member not found or access denied.'];
        $redirect();
    }
    if (isset($_POST['selected_product'])) {
        $id=(int)$_POST['selected_product'];$stmt=$conn->prepare('SELECT * FROM products WHERE id=? AND vendor_id=? LIMIT 1');$stmt->bind_param('ii',$id,$vendorId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($row){$_SESSION['product_info']=$row;$_SESSION['current_dashboard']='products_dashboard';}
        else $_SESSION['errors_create']=['selection'=>'Product not found or access denied.'];
        $redirect();
    }
    if (isset($_POST['selected_cost_item'])) {
        $id=(int)$_POST['selected_cost_item'];$stmt=$conn->prepare('SELECT * FROM cost_items WHERE id=? AND vendor_id=? LIMIT 1');$stmt->bind_param('ii',$id,$vendorId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($row){$_SESSION['cost_item_info']=$row;}
        else $_SESSION['errors_create']=['selection'=>'Cost item not found or access denied.'];
        $redirect();
    }
    if (isset($_POST['selected_form'])) {
        $value=trim((string)$_POST['selected_form']);
        if($value!=='' && strlen($value)<=100) $_SESSION['selected_form']=$value;
        $redirect();
    }
    if (isset($_POST['selected_contact'])) {
        $id=(int)$_POST['selected_contact'];
        $stmt=$conn->prepare('SELECT * FROM contacts WHERE contact_id=? AND (client_id=? OR vendor_id=?) LIMIT 1');$stmt->bind_param('iii',$id,$clientId,$vendorId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($row)$_SESSION['contact_info']=$row;else $_SESSION['errors_create']=['selection'=>'Contact not found or access denied.'];
        $redirect();
    }
}
