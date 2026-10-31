<?php
//vendors/branches/branches.ui.php
declare(strict_types=1);

require_once __DIR__ . '/branches.forms.php';
function show_selected_vendor_form(mysqli $conn): void
{
    if (!isset($_SESSION['current_dashboard'], $_SESSION['selected_pge'])) {
        return;
    }

    $dashboard = $_SESSION['current_dashboard'];
    $page = $_SESSION['selected_pge'];
    $form = $_SESSION['selected_form'] ?? null;

    $routes = [

        'shifts_dashboard' => [
            'create_pge' => [
                'Create Shift' => static fn() => create_shift_form($conn),
                'Create Shift Organogram' => static fn() => create_org_form($conn),
            ],
            'edit_pge' => [
                'Edit Shift' => static fn() => edit_shift_form($conn),
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
