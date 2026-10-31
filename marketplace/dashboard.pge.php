<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/global.shw.php';
require_once __DIR__ . '/../../includes/product_hierarchy.php';

$_SESSION['current_dashboard'] = 'marketplace_dashboard';
$_SESSION['selected_pge'] = 'dashboard_pge';
product_hierarchy_bootstrap($conn);

$great_id = isset($_GET['great_id']) ? (int)$_GET['great_id'] : 0;
$grand_id = isset($_GET['grand_id']) ? (int)$_GET['grand_id'] : 0;
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;

function fetchMarketplaceRows(mysqli $conn, string $query, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($query);
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

function marketplaceLink(array $params): string
{
    $query = http_build_query(array_filter($params, static fn ($value): bool => (int)$value > 0));
    return '/cnrbyte/marketplace/dashboard.pge.php' . ($query !== '' ? '?' . $query : '');
}

$greatCategories = fetchMarketplaceRows(
    $conn,
    "SELECT id, category
     FROM product_great_categories
     ORDER BY category ASC"
);

$grandCategories = $great_id > 0
    ? fetchMarketplaceRows(
        $conn,
        "SELECT id, category
         FROM product_grand_categories
         WHERE great_id = ?
         ORDER BY category ASC",
        'i',
        [$great_id]
    )
    : [];

$parentCategories = $grand_id > 0
    ? fetchMarketplaceRows(
        $conn,
        "SELECT id, category
         FROM product_parent_categories
         WHERE grand_id = ?
         ORDER BY category ASC",
        'i',
        [$grand_id]
    )
    : [];

$childCategories = $parent_id > 0
    ? fetchMarketplaceRows(
        $conn,
        "SELECT id, category
         FROM product_child_categories
         WHERE parent_id = ?
         ORDER BY category ASC",
        'i',
        [$parent_id]
    )
    : [];

$selectedLabels = [
    'great' => '',
    'grand' => '',
    'parent' => '',
    'child' => '',
];

if ($great_id > 0) {
    $greatInfo = fetchMarketplaceRows($conn, "SELECT category FROM product_great_categories WHERE id = ? LIMIT 1", 'i', [$great_id]);
    $selectedLabels['great'] = $greatInfo[0]['category'] ?? '';
}
if ($grand_id > 0) {
    $grandInfo = fetchMarketplaceRows($conn, "SELECT category FROM product_grand_categories WHERE id = ? LIMIT 1", 'i', [$grand_id]);
    $selectedLabels['grand'] = $grandInfo[0]['category'] ?? '';
}
if ($parent_id > 0) {
    $parentInfo = fetchMarketplaceRows($conn, "SELECT category FROM product_parent_categories WHERE id = ? LIMIT 1", 'i', [$parent_id]);
    $selectedLabels['parent'] = $parentInfo[0]['category'] ?? '';
}
if ($child_id > 0) {
    $childInfo = fetchMarketplaceRows($conn, "SELECT category FROM product_child_categories WHERE id = ? LIMIT 1", 'i', [$child_id]);
    $selectedLabels['child'] = $childInfo[0]['category'] ?? '';
}

$products = $child_id > 0
    ? fetchMarketplaceRows(
        $conn,
        "SELECT
            p.id,
            p.vendor_id,
            v.client_id AS user_id,
            v.reg_name AS vendor_name,
            p.product_name,
            p.description,
            p.measure,
            p.product_img,
            p.sale_price
         FROM products p
         INNER JOIN vendors v ON v.id = p.vendor_id
         WHERE p.child = ?
           AND p.vendor_id IS NOT NULL
           AND v.client_id IS NOT NULL
         ORDER BY p.product_name ASC",
        'i',
        [$child_id]
    )
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNRBYTE - Marketplace</title>
    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/main.css">
    <style>
        .marketplace-shell {
            display: grid;
            gap: 24px;
        }

        .hierarchy-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .hierarchy-panel,
        .products-panel,
        .selection-summary {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 10px 24px rgba(3, 78, 34, 0.08);
        }

        .hierarchy-panel h3,
        .products-panel h3,
        .selection-summary h3 {
            margin-top: 0;
            color: #034e22;
        }

        .category-list {
            display: grid;
            gap: 10px;
        }

        .category-link {
            display: block;
            padding: 11px 12px;
            border-radius: 10px;
            background: #f2f8f4;
            color: #123524;
            text-decoration: none;
            border: 1px solid #d8e7dc;
            font-weight: 600;
        }

        .category-link.is-active {
            background: #034e22;
            color: #fff;
            border-color: #034e22;
        }

        .hint-text {
            color: #5f6f66;
            margin: 0;
        }

        .summary-line {
            margin: 6px 0;
            color: #234032;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: #f9fbfa;
            border: 1px solid #dfe9e2;
            border-radius: 14px;
            padding: 14px;
        }

        .product-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .product-card h4 {
            margin: 0 0 8px;
            color: #123524;
        }

        .product-meta,
        .product-description {
            margin: 6px 0;
            color: #4f5f56;
            font-size: 0.95rem;
        }

        .product-price {
            margin: 10px 0 0;
            color: #034e22;
            font-weight: 700;
        }

        .product-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .product-actions form {
            margin: 0;
        }

        @media (max-width: 980px) {
            .hierarchy-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .hierarchy-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <?php  ?>

    <main style="max-width: 1200px; margin: 0 auto; padding: 20px;">
        <div class="marketplace-shell">
            <div>
                <h2 style="color:#034e22; margin:0 0 8px;">Marketplace</h2>
                <p class="hint-text">Database-driven category flow: choose Great, then Grand, then Parent, then Child to view the real products.</p>
            </div>

            <section class="selection-summary">
                <h3>Current Selection</h3>
                <p class="summary-line"><strong>Great:</strong> <?= htmlspecialchars($selectedLabels['great'] !== '' ? $selectedLabels['great'] : 'Not selected') ?></p>
                <p class="summary-line"><strong>Grand:</strong> <?= htmlspecialchars($selectedLabels['grand'] !== '' ? $selectedLabels['grand'] : 'Not selected') ?></p>
                <p class="summary-line"><strong>Parent:</strong> <?= htmlspecialchars($selectedLabels['parent'] !== '' ? $selectedLabels['parent'] : 'Not selected') ?></p>
                <p class="summary-line"><strong>Child:</strong> <?= htmlspecialchars($selectedLabels['child'] !== '' ? $selectedLabels['child'] : 'Not selected') ?></p>
            </section>

            <section class="hierarchy-grid">
                <div class="hierarchy-panel">
                    <h3>Great Categories</h3>
                    <?php if ($greatCategories === []): ?>
                        <p class="hint-text">No great categories found.</p>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($greatCategories as $category): ?>
                                <a
                                    href="<?= htmlspecialchars(marketplaceLink(['great_id' => (int)$category['id']])) ?>"
                                    class="category-link<?= $great_id === (int)$category['id'] ? ' is-active' : '' ?>"
                                >
                                    <?= htmlspecialchars((string)$category['category']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="hierarchy-panel">
                    <h3>Grand Categories</h3>
                    <?php if ($great_id <= 0): ?>
                        <p class="hint-text">Select a great category first.</p>
                    <?php elseif ($grandCategories === []): ?>
                        <p class="hint-text">No grand categories found for this great category.</p>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($grandCategories as $category): ?>
                                <a
                                    href="<?= htmlspecialchars(marketplaceLink(['great_id' => $great_id, 'grand_id' => (int)$category['id']])) ?>"
                                    class="category-link<?= $grand_id === (int)$category['id'] ? ' is-active' : '' ?>"
                                >
                                    <?= htmlspecialchars((string)$category['category']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="hierarchy-panel">
                    <h3>Parent Categories</h3>
                    <?php if ($grand_id <= 0): ?>
                        <p class="hint-text">Select a grand category first.</p>
                    <?php elseif ($parentCategories === []): ?>
                        <p class="hint-text">No parent categories found for this grand category.</p>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($parentCategories as $category): ?>
                                <a
                                    href="<?= htmlspecialchars(marketplaceLink(['great_id' => $great_id, 'grand_id' => $grand_id, 'parent_id' => (int)$category['id']])) ?>"
                                    class="category-link<?= $parent_id === (int)$category['id'] ? ' is-active' : '' ?>"
                                >
                                    <?= htmlspecialchars((string)$category['category']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="hierarchy-panel">
                    <h3>Child Categories</h3>
                    <?php if ($parent_id <= 0): ?>
                        <p class="hint-text">Select a parent category first.</p>
                    <?php elseif ($childCategories === []): ?>
                        <p class="hint-text">No child categories found for this parent category.</p>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($childCategories as $category): ?>
                                <a
                                    href="<?= htmlspecialchars(marketplaceLink(['great_id' => $great_id, 'grand_id' => $grand_id, 'parent_id' => $parent_id, 'child_id' => (int)$category['id']])) ?>"
                                    class="category-link<?= $child_id === (int)$category['id'] ? ' is-active' : '' ?>"
                                >
                                    <?= htmlspecialchars((string)$category['category']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="products-panel">
                <h3>Products</h3>
                <?php if ($child_id <= 0): ?>
                    <p class="hint-text">Select a child category to list products.</p>
                <?php elseif ($products === []): ?>
                    <p class="hint-text">No products found for this child category.</p>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <?php
                            $image = !empty($product['product_img'])
                                ? '/cnrbyte/uploads/products/' . rawurlencode((string)$product['product_img'])
                                : '/cnrbyte/uploads/products/default_product.png';
                            ?>
                            <div class="product-card">
                                <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars((string)$product['product_name']) ?>">
                                <h4><?= htmlspecialchars((string)$product['product_name']) ?></h4>
                                <p class="product-description"><?= htmlspecialchars(mb_strimwidth((string)($product['description'] ?? ''), 0, 140, '...')) ?></p>
                                <p class="product-meta"><strong>Vendor:</strong> <?= htmlspecialchars((string)($product['vendor_name'] ?? '')) ?></p>
                                <p class="product-meta"><strong>Vendor ID:</strong> <?= (int)$product['vendor_id'] ?></p>
                                <p class="product-meta"><strong>User ID:</strong> <?= (int)$product['user_id'] ?></p>
                                <?php if (!empty($product['measure'])): ?>
                                    <p class="product-meta"><strong>Measure:</strong> <?= htmlspecialchars((string)$product['measure']) ?></p>
                                <?php endif; ?>
                                <p class="product-price"><?= htmlspecialchars('R' . number_format((float)$product['sale_price'], 2)) ?></p>
                                <div class="product-actions">
                                    <form action="/cnrbyte/marketplace/products/cart/add_to_cart.run.php" method="post">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars(marketplaceLink([
                                            'great_id' => $great_id,
                                            'grand_id' => $grand_id,
                                            'parent_id' => $parent_id,
                                            'child_id' => $child_id,
                                        ])) ?>">
                                        <button type="submit" class="form-btn">Add To Cart</button>
                                    </form>
                                    <a href="/cnrbyte/marketplace/products/cart/dashboard.pge.php" class="form-btn" style="text-decoration:none;">View Cart</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div style="text-align:center; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                <a href="/cnrbyte/marketplace/dashboard.pge.php" class="form-btn" style="text-decoration:none;">Reset Filters</a>
                <a href="/cnrbyte/clients/dashboard.pge.php" class="form-btn" style="text-decoration:none;">Back</a>
                <a href="/cnrbyte/marketplace/products/cart/dashboard.pge.php" class="form-btn" style="text-decoration:none;">View Cart</a>
            </div>
        </div>
    </main>
</body>
</html>
