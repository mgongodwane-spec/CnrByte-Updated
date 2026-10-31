<?php
//vendors/vendors.ui.php
declare(strict_types=1);

require_once __DIR__ . '/vendors.forms.php';
function show_selected_vendor_form(mysqli $conn): void
{
    if (!isset($_SESSION['current_dashboard'], $_SESSION['selected_pge'])) {
        return;
    }

    $dashboard = $_SESSION['current_dashboard'];
    $page = $_SESSION['selected_pge'];
    $form = $_SESSION['selected_form'] ?? null;

    $routes = [

        'products_dashboard' => [
            'dashboard_pge' => [
                'Products Table' => static fn() => show_products_table($conn),
            ],
            'create_pge' => [
                'Create Product' => static fn() => create_product_form($conn),
                'Create Cost Item' => static fn() => create_cost_item_form($conn),
                'Create Cost of Sale' => static fn() => create_cost_of_sale_form($conn),
                'Create Product Hierarchy' => static fn() => create_product_hierarchy_form($conn),
            ],
            'edit_pge' => [
                'Edit Product' => static fn() => edit_product_form($conn),
                'Sell Product' => static fn() => sell_product_form($conn),
                'Edit Cost Item' => static fn() => edit_cost_item_form($conn),
                'Stock Cost Item' => static fn() => stock_cost_item_form($conn),
            ],
        ],
    ];

    if (!isset($routes[$dashboard][$page])) {
        return;
    }

    if ($form === null || !isset($routes[$dashboard][$page][$form])) {
        $forms = array_keys($routes[$dashboard][$page]);
        if ($forms === []) {
            return;
        }
        $form = $forms[0];
        $_SESSION['selected_form'] = $form;
    }

    $routes[$dashboard][$page][$form]();
}
