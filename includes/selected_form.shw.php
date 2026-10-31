<?php
declare(strict_types=1);
if (!function_exists('show_selected_form')) {
    function show_selected_form(mysqli $conn): void {
        if (function_exists('show_selected_vendor_form')) {
            show_selected_vendor_form($conn);
            return;
        }
        if (function_exists('show_selected_form_content')) {
            show_selected_form_content($conn);
            return;
        }
        echo '<p class="cnr-empty-state">No form is available for this page.</p>';
    }
}
