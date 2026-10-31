<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

if(!isset($_GET['great_id'])) exit(json_encode([]));

$great_id = intval($_GET['great_id']);
$stmt = $conn->prepare("SELECT id, category FROM product_grand_categories WHERE great_id = ?");
if (!$stmt) exit(json_encode([]));
$stmt->bind_param('i', $great_id);
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
