<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
if (isset($_SESSION['vendor_info']['id'])) {
    header('Location: /cnrbyte/vendors/dashboard.pge.php');
} elseif (isset($_SESSION['user_info']['id'])) {
    header('Location: /cnrbyte/clients/dashboard.pge.php');
} else {
    header('Location: /cnrbyte/signup_login/signup_login.pge.php');
}
exit;
