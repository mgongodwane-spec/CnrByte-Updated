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

        'staff_dashboard' => [
            'create_pge' => [
                'Create Staff' => static fn() => create_staff_form($conn),
            ],
            'edit_pge' => [
                'Edit Staff Qualification' => static fn() => edit_staff_qualification_form($conn),
                'Edit Staff Contract' => static fn() => edit_staff_contract_form($conn),
                'Edit Staff Picture' => static fn() => edit_staff_picture_form(),
                'Edit Staff Financials' => static fn() => edit_financial_form($conn),
                'Edit Staff Physical Address' => static fn() => edit_physical_address_form($conn),
                'Edit Staff Postal Address' => static fn() => edit_postal_address_form($conn),
                'Edit Staff Contacts' => static fn() => edit_contacts_form($conn),
                'Edit Staff Documents' => static fn() => edit_documents_form($conn),
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
