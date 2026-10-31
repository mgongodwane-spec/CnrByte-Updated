<?php
declare(strict_types=1);
if (!function_exists('show_menu_btns')) {
    function show_menu_btns(mysqli $conn, ?string $selectedPage = null): void {
        $page = $selectedPage ?? ($_SESSION['selected_pge'] ?? 'dashboard_pge');
        $links = ['dashboard_pge' => 'Overview', 'create_pge' => 'Create', 'edit_pge' => 'Manage'];
        echo '<div class="cnr-action-menu" aria-label="Page actions">';
        foreach ($links as $key => $label) {
            $current = $page === $key ? ' aria-current="page"' : '';
            $url = $key === 'dashboard_pge' ? 'dashboard.pge.php' : ($key === 'create_pge' ? 'create.pge.php' : 'edit.pge.php');
            echo '<a class="cnr-button cnr-button-secondary" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $current . '>' .
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        echo '</div>';
    }
}
