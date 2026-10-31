<?php
// Report all mysqli errors as exceptions for easier debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$db   = 'cnrbyte'; // Updated database name
$user = 'root';    // Default for XAMPP
$pass = '';        // Default for XAMPP

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed. Check if 'cnrbyte' exists.");
}