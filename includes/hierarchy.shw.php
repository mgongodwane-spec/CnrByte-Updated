<?php
declare(strict_types=1);
if (!function_exists('show_hierarchy')) {
    function show_hierarchy(mysqli $conn): void {
        $items = [
            'Vendor Dashboard' => '/cnrbyte/vendors/dashboard.pge.php',
            'Branches' => '/cnrbyte/vendors/branches/dashboard.pge.php',
            'Shifts' => '/cnrbyte/vendors/branches/shifts/dashboard.pge.php',
            'Staff' => '/cnrbyte/vendors/branches/shifts/staff/dashboard.pge.php',
            'HR & Payroll' => '/cnrbyte/vendors/branches/shifts/staff/hr/dashboard.pge.php',
            'Products' => '/cnrbyte/marketplace/products/dashboard.pge.php',
            'Marketplace' => '/cnrbyte/marketplace/dashboard.pge.php',
        ];
        echo '<nav class="cnr-module-nav" aria-label="Module navigation">';
        foreach ($items as $label => $url) {
            echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
                 htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        echo '</nav>';
    }
}
