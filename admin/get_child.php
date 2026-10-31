<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

if(!isset($_GET['parent_id'])) exit(json_encode([]));

$parent_id = intval($_GET['parent_id']);
$stmt = $conn->prepare("SELECT id, category FROM product_child_categories WHERE parent_id = ?");
if (!$stmt) exit(json_encode([]));
$stmt->bind_param('i', $parent_id);
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
