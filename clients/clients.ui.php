<?php
declare(strict_types=1);

/**
 * Client workspace compatibility renderer.
 * The former version required a missing vendors/vendors.forms.php file and exposed
 * form callbacks that were not present in this checkpoint. Account settings now live
 * in clients/profile.pge.php; this renderer keeps legacy callers safe and navigable.
 */
function show_selected_client_form(mysqli $conn): void
{
    $page = (string)($_SESSION['selected_pge'] ?? 'dashboard_pge');
    if ($page === 'edit_pge' || $page === 'create_pge' || $page === 'profile_pge') {
        echo '<div class="cnr-form-card"><h2>Profile and contact settings</h2><p>Manage your account details, contact information and addresses in the profile settings page.</p><a class="cnr-button" href="/cnrbyte/clients/profile.pge.php">Open profile settings</a></div>';
        return;
    }
    echo '<div class="cnr-form-card"><h2>Client workspace</h2><p>Use the shared navigation to open profile settings, marketplace, financial records, messages and direct chats.</p><a class="cnr-button" href="/cnrbyte/clients/profile.pge.php">Profile and contact settings</a></div>';
}
