<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
cnrbyte_auth_require_post_csrf();
if (empty($_SESSION['user_info']['id'])) { http_response_code(403); exit('Please sign in first.'); }
http_response_code(403);
exit('Application status is controlled by the administrator. You cannot approve or decline your own application.');
