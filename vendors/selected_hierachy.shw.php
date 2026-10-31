<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/hierarchy.shw.php';
if (isset($conn) && $conn instanceof mysqli) show_hierarchy($conn);
