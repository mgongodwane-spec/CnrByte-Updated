<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

function getChildren($conn, $parent_id) {
    $stmt = $conn->prepare("SELECT c.id, c.category, COUNT(pr.id) AS product_count
        FROM product_child_categories c
        LEFT JOIN products pr ON pr.child = c.id AND pr.parent = c.parent_id AND pr.grand = c.grand_id AND pr.great = c.great_id
        WHERE c.parent_id = ? GROUP BY c.id, c.category ORDER BY c.category ASC");
    if (!$stmt) return '';
    $stmt->bind_param('i', $parent_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $html = "";
    while ($row = $res->fetch_assoc()) {
        $link = '/cnrbyte/marketplace/dashboard.pge.php?child_id=' . urlencode($row['id']);
        $count = (int)($row['product_count'] ?? 0);
        $badge = '<small class="category-product-count">' . $count . ' product' . ($count === 1 ? '' : 's') . '</small>';
        $html .= "<li>🟢 <a href='" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') . "</a> " . $badge . "</li>";
    }
    $stmt->close();
    return $html ? "<ul>$html</ul>" : "";
}

function getParents($conn, $grand_id) {
    $stmt = $conn->prepare("SELECT p.id, p.category, COUNT(DISTINCT pr.id) AS product_count
        FROM product_parent_categories p
        LEFT JOIN products pr ON pr.parent = p.id AND pr.grand = p.grand_id AND pr.great = p.great_id
        WHERE p.grand_id = ? GROUP BY p.id, p.category ORDER BY p.category ASC");
    if (!$stmt) return '';
    $stmt->bind_param('i', $grand_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $html = "";
    while ($row = $res->fetch_assoc()) {
        $children = getChildren($conn, $row['id']);
        $link = '/cnrbyte/marketplace/dashboard.pge.php?parent_id=' . urlencode($row['id']);
        $toggle = $children ? "<span class='toggle'>▼</span>" : "";
        $count = (int)($row['product_count'] ?? 0);
        $badge = '<small class="category-product-count">' . $count . ' product' . ($count === 1 ? '' : 's') . '</small>';
        $html .= "<li>$toggle 📘 <a href='" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') . "</a> " . $badge . $children . "</li>";
    }
    $stmt->close();
    return $html ? "<ul>$html</ul>" : "";
}

function getGrands($conn, $great_id) {
    $stmt = $conn->prepare("SELECT g.id, g.category, COUNT(DISTINCT pr.id) AS product_count
        FROM product_grand_categories g
        LEFT JOIN products pr ON pr.grand = g.id AND pr.great = g.great_id
        WHERE g.great_id = ? GROUP BY g.id, g.category ORDER BY g.category ASC");
    if (!$stmt) return '';
    $stmt->bind_param('i', $great_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $html = "";
    while ($row = $res->fetch_assoc()) {
        $parents = getParents($conn, $row['id']);
        $link = '/cnrbyte/marketplace/dashboard.pge.php?grand_id=' . urlencode($row['id']);
        $toggle = $parents ? "<span class='toggle'>▼</span>" : "";
        $count = (int)($row['product_count'] ?? 0);
        $badge = '<small class="category-product-count">' . $count . ' product' . ($count === 1 ? '' : 's') . '</small>';
        $html .= "<li>$toggle 📗 <a href='" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') . "</a> " . $badge . $parents . "</li>";
    }
    $stmt->close();
    return $html ? "<ul>$html</ul>" : "";
}

function getGreats($conn) {
    $result = mysqli_query($conn, "SELECT g.id, g.category, COUNT(DISTINCT pr.id) AS product_count
        FROM product_great_categories g
        LEFT JOIN products pr ON pr.great = g.id
        GROUP BY g.id, g.category ORDER BY g.category ASC");
    $html = "";
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $grands = getGrands($conn, $row['id']);
            $link = '/cnrbyte/marketplace/dashboard.pge.php?great_id=' . urlencode($row['id']);
            $toggle = $grands ? "<span class='toggle'>▼</span>" : "";
            $count = (int)($row['product_count'] ?? 0);
            $badge = '<small class="category-product-count">' . $count . ' product' . ($count === 1 ? '' : 's') . '</small>';
            $html .= "<li>$toggle 📂 <a href='" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "' class='highlight'>" . htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') . "</a> " . $badge . $grands . "</li>";
        }
    }
    return "<ul>$html</ul>";
}

echo getGreats($conn);
