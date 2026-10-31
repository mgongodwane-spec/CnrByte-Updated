<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/conn.dbh.php';
require_once __DIR__ . '/../includes/product_hierarchy.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}
if (!cnrbyte_auth_csrf_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid or expired form token. Refresh the page and try again.');
}

product_hierarchy_bootstrap($conn);
$level = strtolower(trim((string)($_POST['level'] ?? '')));
$raw = (string)($_POST['categories'] ?? '');
$categories = array_values(array_unique(array_filter(
    array_map('product_hierarchy_normalize_name', explode(',', $raw)),
    static fn(string $value): bool => $value !== ''
)));

if (!$categories) {
    http_response_code(400);
    exit('Enter at least one category name.');
}

$greatId = (int)($_POST['great_id'] ?? 0);
$grandId = (int)($_POST['grand_id'] ?? 0);
$parentId = (int)($_POST['parent_id'] ?? 0);

try {
    $conn->begin_transaction();
    foreach ($categories as $category) {
        if (mb_strlen($category) > 255) {
            throw new RuntimeException('Category names must be 255 characters or fewer.');
        }

        switch ($level) {
            case 'great':
                product_hierarchy_ensure_great_category($conn, $category);
                break;

            case 'grand':
                if (!product_hierarchy_get_one($conn, 'SELECT id FROM product_great_categories WHERE id = ?', 'i', [$greatId])) {
                    throw new RuntimeException('Select a valid great category.');
                }
                product_hierarchy_ensure_grand_category($conn, $greatId, $category);
                break;

            case 'parent':
                $grand = product_hierarchy_get_one($conn, 'SELECT id, great_id FROM product_grand_categories WHERE id = ?', 'i', [$grandId]);
                if (!$grand || (int)$grand['great_id'] !== $greatId) {
                    throw new RuntimeException('The selected grand category does not belong to the selected great category.');
                }
                product_hierarchy_ensure_parent_category($conn, $greatId, $grandId, $category);
                break;

            case 'child':
                $parent = product_hierarchy_get_one($conn, 'SELECT id, grand_id, great_id FROM product_parent_categories WHERE id = ?', 'i', [$parentId]);
                if (!$parent || (int)$parent['grand_id'] !== $grandId || (int)$parent['great_id'] !== $greatId) {
                    throw new RuntimeException('The selected parent category does not belong to the selected grand and great categories.');
                }
                product_hierarchy_ensure_child_category($conn, $greatId, $grandId, $parentId, $category);
                break;

            default:
                throw new RuntimeException('Invalid category level.');
        }
    }
    $conn->commit();
    echo 'Category saved successfully. Existing duplicates were reused.';
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
