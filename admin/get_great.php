<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

$result = mysqli_query($conn, "SELECT id, category FROM product_great_categories ORDER BY category ASC");
$all = [];
if ($result) {
	while ($row = mysqli_fetch_assoc($result)) {
		$all[] = $row;
	}
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($all);
