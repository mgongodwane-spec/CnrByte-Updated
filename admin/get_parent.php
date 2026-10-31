<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

if(!isset($_GET['grand_id'])) exit(json_encode([]));

$grand_id = intval($_GET['grand_id']);
$stmt = $conn->prepare("SELECT id, category FROM product_parent_categories WHERE grand_id = ?");
if (!$stmt) exit(json_encode([]));
$stmt->bind_param('i', $grand_id);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
if ($res) {
	while ($row = $res->fetch_assoc()) {
		$rows[] = $row;
	}
}

echo json_encode($rows);
?>
