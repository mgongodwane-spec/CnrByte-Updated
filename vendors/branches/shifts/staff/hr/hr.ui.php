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

        'hr_dashboard' => [
            'dashboard_pge' => [
                'Shift Roster' => static fn() => show_shift_roster_table($conn),
                'Staff Roster' => static fn() => show_staff_roster_table($conn),
                'Payslip' => static fn() => show_staff_payslip_table($conn),
            ],
            'create_pge' => [
                'Create Shift Roster' => static fn() => create_shift_roster_form($conn),
                'Create Staff Roster' => static fn() => create_staff_roster_form($conn),
                'Create Staff Leave' => static fn() => create_staff_leave_form($conn),
                'Create HR Rates' => static fn() => create_hr_rates(),
                'Create Earnings' => static fn() => create_earnings_form($conn),
                'Create Deductions' => static fn() => create_deductions_form($conn),
            ],
            'edit_pge' => [
                'Edit Shift Roster' => static fn() => edit_shift_roster_form($conn),
                'Edit Staff Roster' => static fn() => edit_staff_roster_form($conn),
                'Edit Staff Leave' => static fn() => edit_staff_leave_form($conn),
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
