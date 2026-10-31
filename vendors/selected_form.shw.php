<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/selected_form.shw.php';
if (isset($conn) && $conn instanceof mysqli) show_selected_form($conn);
