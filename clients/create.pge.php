<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.run.php';
if (empty($_SESSION['user_info']['id'])) { header('Location: /cnrbyte/signup_login/signup_login.pge.php'); exit; }
header('Location: /cnrbyte/clients/profile.pge.php');
exit;
