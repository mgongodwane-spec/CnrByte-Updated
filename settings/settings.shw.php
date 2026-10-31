<?php
declare(strict_types=1);

/** Render and clear one-time settings success/error messages safely. */
if (!function_exists('settings_render_flash_messages')) {
    function settings_render_flash_messages(): void
    {
        if (!empty($_SESSION['success_create']) && is_array($_SESSION['success_create'])) {
            echo '<div class="success-title" role="status"><strong>Success:</strong><br>';
            foreach ($_SESSION['success_create'] as $message) {
                echo htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') . '<br>';
            }
            echo '</div>';
            unset($_SESSION['success_create']);
        }
        if (!empty($_SESSION['errors_create']) && is_array($_SESSION['errors_create'])) {
            echo '<div class="error-title" role="alert"><strong>There was a problem:</strong><br>';
            foreach ($_SESSION['errors_create'] as $message) {
                echo htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') . '<br>';
            }
            echo '</div>';
            unset($_SESSION['errors_create']);
        }
    }
}
