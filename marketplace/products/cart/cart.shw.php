<?php
declare(strict_types=1);

require_once __DIR__ . '/cart.func.php';

function render_cart_notice(): void
{
    $notice = cart_pull_notice();
    if (!$notice) {
        return;
    }

    $background = $notice['type'] === 'error' ? '#fde8e8' : '#e8f7ee';
    $color = $notice['type'] === 'error' ? '#9b1c1c' : '#166534';

    echo "<div style='background:{$background};color:{$color};padding:12px 14px;border-radius:10px;margin-bottom:16px;'>";
    echo htmlspecialchars((string)$notice['message'], ENT_QUOTES, 'UTF-8');
    echo "</div>";
}

function render_cart_items(mysqli $conn): void
{
    $items = cart_get_items($conn);

    if ($items === []) {
        echo "<div style='background:#fff;padding:24px;border-radius:14px;'>";
        echo "<h3 style='margin-top:0;'>Your cart is empty</h3>";
        echo "<p style='margin-bottom:16px;color:#4b5563;'>Add products from the marketplace and they will appear here.</p>";
        echo "<a href='/cnrbyte/marketplace/dashboard.pge.php' class='form-btn' style='text-decoration:none;'>Go To Marketplace</a>";
        echo "</div>";
        return;
    }

    echo "<div style='display:grid;gap:16px;'>";

    foreach ($items as $item) {
        $img = $item['product_img'] !== ''
            ? '/cnrbyte/uploads/products/' . rawurlencode($item['product_img'])
            : '/cnrbyte/uploads/products/default_product.png';

        echo "<div style='display:grid;grid-template-columns:110px 1fr;gap:16px;background:#fff;padding:16px;border-radius:14px;align-items:start;'>";
        echo "<img src='" . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') . "' style='width:110px;height:110px;object-fit:cover;border-radius:10px;'>";
        echo "<div>";
        echo "<div style='display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;'>";
        echo "<div>";
        echo "<h3 style='margin:0 0 6px;'>" . htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') . "</h3>";
        echo "<p style='margin:0 0 6px;color:#4b5563;'>" . htmlspecialchars(mb_strimwidth($item['description'], 0, 160, '...'), ENT_QUOTES, 'UTF-8') . "</p>";
        echo "<p style='margin:0;color:#6b7280;font-size:0.9rem;'>Vendor: " . htmlspecialchars($item['vendor_name'], ENT_QUOTES, 'UTF-8') . "</p>";
        if ($item['measure'] !== '') {
            echo "<p style='margin:4px 0 0;color:#6b7280;font-size:0.9rem;'>Unit: " . htmlspecialchars($item['measure'], ENT_QUOTES, 'UTF-8') . "</p>";
        }
        echo "</div>";
        echo "<div style='text-align:right;min-width:140px;'>";
        echo "<p style='margin:0;color:#6b7280;'>Price</p>";
        echo "<p style='margin:4px 0 0;font-weight:700;'>" . cart_format_currency((float)$item['sale_price']) . "</p>";
        echo "<p style='margin:12px 0 0;color:#6b7280;'>Line Total</p>";
        echo "<p style='margin:4px 0 0;font-weight:700;'>" . cart_format_currency((float)$item['line_total']) . "</p>";
        echo "</div>";
        echo "</div>";

        echo "<div style='display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;align-items:center;'>";
        echo "<form action='/cnrbyte/marketplace/products/cart/edit.run.php' method='post' style='display:flex;gap:10px;align-items:center;flex-wrap:wrap;'>";
        echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') . "'>";
        echo "<input type='hidden' name='action' value='update'>";
        echo "<input type='hidden' name='product_id' value='" . (int)$item['id'] . "'>";
        echo "<input type='number' name='quantity' min='1' value='" . (int)$item['quantity'] . "' style='width:84px;padding:10px;border:1px solid #d1d5db;border-radius:8px;'>";
        echo "<button type='submit' class='form-btn'>Update Quantity</button>";
        echo "</form>";

        echo "<form action='/cnrbyte/marketplace/products/cart/edit.run.php' method='post'>";
        echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') . "'>";
        echo "<input type='hidden' name='action' value='remove'>";
        echo "<input type='hidden' name='product_id' value='" . (int)$item['id'] . "'>";
        echo "<button type='submit' class='form-btn' style='background:#991b1b;'>Remove</button>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    echo "</div>";
}
