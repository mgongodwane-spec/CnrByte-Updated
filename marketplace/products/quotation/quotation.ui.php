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

        'vendors_dashboard' => [
            'create_pge' => [
                'Create Vendor' => static fn() => create_vendor_form($conn),
                'Create Vendor Organogram' => static fn() => create_org_form($conn),
            ],
            'edit_pge' => [
                'Edit Vendor Logo' => static fn() => edit_vendor_logo_form(),
                'Edit Vendor Profile' => static fn() => edit_vendor_profile_form(),
                'Edit Vendor Financials' => static fn() => edit_financial_form($conn),
                'Edit Vendor Physical Address' => static fn() => edit_physical_address_form($conn),
                'Edit Vendor Postal Address' => static fn() => edit_postal_address_form($conn),
                'Edit Vendor Contacts' => static fn() => edit_contacts_form($conn),
                'Edit Vendor Documents' => static fn() => edit_documents_form($conn),
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
