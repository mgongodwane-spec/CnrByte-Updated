<?php
declare(strict_types=1);

function product_hierarchy_bootstrap(mysqli $conn): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    product_hierarchy_ensure_tables($conn);
    product_hierarchy_sync_legacy_categories($conn);
    product_hierarchy_sync_products($conn);

    $bootstrapped = true;
}

function product_hierarchy_ensure_tables(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS product_great_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS product_grand_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            great_id INT NOT NULL,
            category VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS product_parent_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grand_id INT NOT NULL,
            great_id INT NOT NULL,
            category VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS product_child_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL,
            grand_id INT NOT NULL,
            great_id INT NOT NULL,
            category VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function product_hierarchy_get_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '' && $params !== []) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

function product_hierarchy_get_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = product_hierarchy_get_rows($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function product_hierarchy_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result !== false && $result->num_rows > 0;
}

function product_hierarchy_normalize_name(string $category): string
{
    return trim(preg_replace('/\s+/', ' ', $category) ?? '');
}

function product_hierarchy_ensure_great_category(mysqli $conn, string $category): int
{
    $category = product_hierarchy_normalize_name($category);
    if ($category === '') {
        return 0;
    }

    $existing = product_hierarchy_get_one(
        $conn,
        "SELECT id FROM product_great_categories WHERE category = ? LIMIT 1",
        's',
        [$category]
    );
    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $conn->prepare("INSERT INTO product_great_categories (category) VALUES (?)");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('s', $category);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return $id;
}

function product_hierarchy_ensure_grand_category(mysqli $conn, int $greatId, string $category): int
{
    $category = product_hierarchy_normalize_name($category);
    if ($greatId <= 0 || $category === '') {
        return 0;
    }

    $existing = product_hierarchy_get_one(
        $conn,
        "SELECT id FROM product_grand_categories WHERE great_id = ? AND category = ? LIMIT 1",
        'is',
        [$greatId, $category]
    );
    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $conn->prepare("INSERT INTO product_grand_categories (great_id, category) VALUES (?, ?)");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('is', $greatId, $category);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return $id;
}

function product_hierarchy_ensure_parent_category(mysqli $conn, int $greatId, int $grandId, string $category): int
{
    $category = product_hierarchy_normalize_name($category);
    if ($greatId <= 0 || $grandId <= 0 || $category === '') {
        return 0;
    }

    $existing = product_hierarchy_get_one(
        $conn,
        "SELECT id FROM product_parent_categories WHERE great_id = ? AND grand_id = ? AND category = ? LIMIT 1",
        'iis',
        [$greatId, $grandId, $category]
    );
    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $conn->prepare(
        "INSERT INTO product_parent_categories (grand_id, great_id, category) VALUES (?, ?, ?)"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('iis', $grandId, $greatId, $category);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return $id;
}

function product_hierarchy_ensure_child_category(mysqli $conn, int $greatId, int $grandId, int $parentId, string $category): int
{
    $category = product_hierarchy_normalize_name($category);
    if ($greatId <= 0 || $grandId <= 0 || $parentId <= 0 || $category === '') {
        return 0;
    }

    $existing = product_hierarchy_get_one(
        $conn,
        "SELECT id FROM product_child_categories WHERE great_id = ? AND grand_id = ? AND parent_id = ? AND category = ? LIMIT 1",
        'iiis',
        [$greatId, $grandId, $parentId, $category]
    );
    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $conn->prepare(
        "INSERT INTO product_child_categories (parent_id, grand_id, great_id, category) VALUES (?, ?, ?, ?)"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('iiis', $parentId, $grandId, $greatId, $category);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return $id;
}

function product_hierarchy_get_categories(mysqli $conn, string $rank, ?int $parentId = null): array
{
    return match ($rank) {
        'great' => product_hierarchy_get_rows(
            $conn,
            "SELECT id, category FROM product_great_categories ORDER BY category ASC"
        ),
        'grand' => $parentId && $parentId > 0
            ? product_hierarchy_get_rows(
                $conn,
                "SELECT id, category FROM product_grand_categories WHERE great_id = ? ORDER BY category ASC",
                'i',
                [$parentId]
            )
            : product_hierarchy_get_rows(
                $conn,
                "SELECT id, category FROM product_grand_categories ORDER BY category ASC"
            ),
        'parent' => $parentId && $parentId > 0
            ? product_hierarchy_get_rows(
                $conn,
                "SELECT id, category FROM product_parent_categories WHERE grand_id = ? ORDER BY category ASC",
                'i',
                [$parentId]
            )
            : product_hierarchy_get_rows(
                $conn,
                "SELECT id, category FROM product_parent_categories ORDER BY category ASC"
            ),
        'child' => $parentId && $parentId > 0
            ? product_hierarchy_get_rows(
                $conn,
                "SELECT id, category FROM product_child_categories WHERE parent_id = ? ORDER BY category ASC",
                'i',
                [$parentId]
            )
            : product_hierarchy_get_rows(
                $conn,
                "SELECT id, category FROM product_child_categories ORDER BY category ASC"
            ),
        default => [],
    };
}

function product_hierarchy_get_path(mysqli $conn, int $childId): ?array
{
    if ($childId <= 0) {
        return null;
    }

    return product_hierarchy_get_one(
        $conn,
        "SELECT
            g.id AS great_id,
            g.category AS great,
            gr.id AS grand_id,
            gr.category AS grand,
            p.id AS parent_id,
            p.category AS parent,
            c.id AS child_id,
            c.category AS child
         FROM product_child_categories c
         INNER JOIN product_parent_categories p
           ON p.id = c.parent_id AND p.grand_id = c.grand_id AND p.great_id = c.great_id
         INNER JOIN product_grand_categories gr
           ON gr.id = p.grand_id AND gr.great_id = p.great_id
         INNER JOIN product_great_categories g ON g.id = gr.great_id
         WHERE c.id = ?
         LIMIT 1",
        'i',
        [$childId]
    );
}

function product_hierarchy_is_valid_path(mysqli $conn, int $greatId, int $grandId, int $parentId, int $childId): bool
{
    if ($greatId <= 0 || $grandId <= 0 || $parentId <= 0 || $childId <= 0) {
        return false;
    }

    // Validate the complete relational chain; do not trust redundant IDs
    // stored on the child row without checking its actual ancestors.
    $match = product_hierarchy_get_one(
        $conn,
        "SELECT c.id
         FROM product_child_categories c
         INNER JOIN product_parent_categories p
           ON p.id = c.parent_id AND p.grand_id = c.grand_id AND p.great_id = c.great_id
         INNER JOIN product_grand_categories gr
           ON gr.id = p.grand_id AND gr.great_id = p.great_id
         INNER JOIN product_great_categories g
           ON g.id = gr.great_id
         WHERE c.id = ? AND g.id = ? AND gr.id = ? AND p.id = ?
         LIMIT 1",
        'iiii',
        [$childId, $greatId, $grandId, $parentId]
    );

    return $match !== null;
}

function product_hierarchy_sync_legacy_categories(mysqli $conn): void
{
    if (!product_hierarchy_table_exists($conn, 'categories')) {
        return;
    }

    $legacyWhere = "(type = 'products' OR type IS NULL OR type = '')";

    $greatMap = [];
    foreach (product_hierarchy_get_rows($conn, "SELECT id, category FROM categories WHERE {$legacyWhere} AND rank = 'great' ORDER BY id ASC") as $row) {
        $greatMap[(int)$row['id']] = product_hierarchy_ensure_great_category($conn, (string)$row['category']);
    }

    $grandMap = [];
    foreach (product_hierarchy_get_rows($conn, "SELECT id, parent_id, category FROM categories WHERE {$legacyWhere} AND rank = 'grand' ORDER BY id ASC") as $row) {
        $legacyGreatId = (int)$row['parent_id'];
        $greatId = $greatMap[$legacyGreatId] ?? 0;
        if ($greatId <= 0) {
            continue;
        }

        $grandMap[(int)$row['id']] = product_hierarchy_ensure_grand_category($conn, $greatId, (string)$row['category']);
    }

    $parentMap = [];
    foreach (product_hierarchy_get_rows($conn, "SELECT id, parent_id, category FROM categories WHERE {$legacyWhere} AND rank = 'parent' ORDER BY id ASC") as $row) {
        $legacyGrandId = (int)$row['parent_id'];
        $grandId = $grandMap[$legacyGrandId] ?? 0;
        if ($grandId <= 0) {
            continue;
        }

        $legacyGrand = product_hierarchy_get_one(
            $conn,
            "SELECT parent_id FROM categories WHERE id = ? AND rank = 'grand' LIMIT 1",
            'i',
            [$legacyGrandId]
        );
        $greatId = $legacyGrand ? ($greatMap[(int)$legacyGrand['parent_id']] ?? 0) : 0;
        if ($greatId <= 0) {
            continue;
        }

        $parentMap[(int)$row['id']] = product_hierarchy_ensure_parent_category($conn, $greatId, $grandId, (string)$row['category']);
    }

    foreach (product_hierarchy_get_rows($conn, "SELECT id, parent_id, category FROM categories WHERE {$legacyWhere} AND rank = 'child' ORDER BY id ASC") as $row) {
        $legacyParentId = (int)$row['parent_id'];
        $parentId = $parentMap[$legacyParentId] ?? 0;
        if ($parentId <= 0) {
            continue;
        }

        $legacyParent = product_hierarchy_get_one(
            $conn,
            "SELECT parent_id FROM categories WHERE id = ? AND rank = 'parent' LIMIT 1",
            'i',
            [$legacyParentId]
        );
        $legacyGrandId = $legacyParent ? (int)$legacyParent['parent_id'] : 0;
        $grandId = $grandMap[$legacyGrandId] ?? 0;

        $legacyGrand = $legacyGrandId > 0
            ? product_hierarchy_get_one(
                $conn,
                "SELECT parent_id FROM categories WHERE id = ? AND rank = 'grand' LIMIT 1",
                'i',
                [$legacyGrandId]
            )
            : null;
        $greatId = $legacyGrand ? ($greatMap[(int)$legacyGrand['parent_id']] ?? 0) : 0;

        if ($greatId <= 0 || $grandId <= 0) {
            continue;
        }

        product_hierarchy_ensure_child_category($conn, $greatId, $grandId, $parentId, (string)$row['category']);
    }
}

function product_hierarchy_sync_products(mysqli $conn): void
{
    if (!product_hierarchy_table_exists($conn, 'products')) {
        return;
    }

    $products = product_hierarchy_get_rows($conn, "SELECT id, great, grand, parent, child FROM products ORDER BY id ASC");
    $update = $conn->prepare("UPDATE products SET great = ?, grand = ?, parent = ?, child = ? WHERE id = ?");
    if (!$update) {
        return;
    }

    foreach ($products as $product) {
        $productId = (int)$product['id'];
        $greatId = (int)($product['great'] ?? 0);
        $grandId = (int)($product['grand'] ?? 0);
        $parentId = (int)($product['parent'] ?? 0);
        $childId = (int)($product['child'] ?? 0);

        $globalChild = $childId > 0
            ? product_hierarchy_get_one(
                $conn,
                "SELECT id, great_id, grand_id, parent_id FROM product_child_categories WHERE id = ? LIMIT 1",
                'i',
                [$childId]
            )
            : null;

        if ($globalChild) {
            $newGreatId = (int)$globalChild['great_id'];
            $newGrandId = (int)$globalChild['grand_id'];
            $newParentId = (int)$globalChild['parent_id'];
            $newChildId = (int)$globalChild['id'];

            if ($greatId !== $newGreatId || $grandId !== $newGrandId || $parentId !== $newParentId) {
                $update->bind_param('iiiii', $newGreatId, $newGrandId, $newParentId, $newChildId, $productId);
                $update->execute();
            }
            continue;
        }

        if (!product_hierarchy_table_exists($conn, 'categories') || $childId <= 0) {
            continue;
        }

        $legacyChild = product_hierarchy_get_one(
            $conn,
            "SELECT id, parent_id, category FROM categories WHERE id = ? AND rank = 'child' LIMIT 1",
            'i',
            [$childId]
        );
        if (!$legacyChild) {
            continue;
        }

        $legacyParent = product_hierarchy_get_one(
            $conn,
            "SELECT id, parent_id, category FROM categories WHERE id = ? AND rank = 'parent' LIMIT 1",
            'i',
            [(int)$legacyChild['parent_id']]
        );
        if (!$legacyParent) {
            continue;
        }

        $legacyGrand = product_hierarchy_get_one(
            $conn,
            "SELECT id, parent_id, category FROM categories WHERE id = ? AND rank = 'grand' LIMIT 1",
            'i',
            [(int)$legacyParent['parent_id']]
        );
        if (!$legacyGrand) {
            continue;
        }

        $legacyGreat = product_hierarchy_get_one(
            $conn,
            "SELECT id, category FROM categories WHERE id = ? AND rank = 'great' LIMIT 1",
            'i',
            [(int)$legacyGrand['parent_id']]
        );
        if (!$legacyGreat) {
            continue;
        }

        $newGreatId = product_hierarchy_ensure_great_category($conn, (string)$legacyGreat['category']);
        $newGrandId = product_hierarchy_ensure_grand_category($conn, $newGreatId, (string)$legacyGrand['category']);
        $newParentId = product_hierarchy_ensure_parent_category($conn, $newGreatId, $newGrandId, (string)$legacyParent['category']);
        $newChildId = product_hierarchy_ensure_child_category($conn, $newGreatId, $newGrandId, $newParentId, (string)$legacyChild['category']);

        if ($newGreatId > 0 && $newGrandId > 0 && $newParentId > 0 && $newChildId > 0) {
            $update->bind_param('iiiii', $newGreatId, $newGrandId, $newParentId, $newChildId, $productId);
            $update->execute();
        }
    }

    $update->close();
}
