<?php
declare(strict_types=1);

/** Render the vendor approval queue for administrators. */
function show_pending_vendor_approvals(mysqli $conn): void
{
    $sql = "SELECT v.id AS vendor_id, v.reg_name, v.reg_no, v.email AS vendor_email,
                   v.status, v.created_at, c.username, c.email AS account_email
            FROM vendors v
            LEFT JOIN clients c ON c.id = COALESCE(v.user_id, v.client_id)
            WHERE v.status = 'pending'
            ORDER BY v.created_at ASC, v.id ASC";
    $result = $conn->query($sql);
    if (!$result) {
        error_log('CnrByte pending vendor queue query failed: ' . $conn->error);
        echo '<section class="cnr-admin-card"><h3>Vendor approvals</h3><p class="error-title">The approval queue could not be loaded. Please try again later.</p></section>';
        return;
    }

    echo '<section class="cnr-admin-card" aria-labelledby="pending-vendors-title">';
    echo '<h3 id="pending-vendors-title">Pending vendor approvals</h3>';
    echo '<p>Review vendor registrations before granting marketplace access.</p>';
    if ($result->num_rows === 0) {
        echo '<p>No vendors are currently waiting for approval.</p></section>';
        $result->free();
        return;
    }

    echo '<div class="cnr-table-wrap"><table><thead><tr><th>Business</th><th>Registration</th><th>Account</th><th>Submitted</th><th>Action</th></tr></thead><tbody>';
    while ($vendor = $result->fetch_assoc()) {
        $vendorId = (int)($vendor['vendor_id'] ?? 0);
        $business = htmlspecialchars((string)($vendor['reg_name'] ?? 'Unnamed business'), ENT_QUOTES, 'UTF-8');
        $regNo = htmlspecialchars((string)($vendor['reg_no'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars((string)($vendor['username'] ?? 'Unknown account'), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string)($vendor['account_email'] ?? $vendor['vendor_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $created = htmlspecialchars((string)($vendor['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo '<tr><td>' . $business . '</td><td>' . $regNo . '</td><td>' . $username . '<br><small>' . $email . '</small></td><td>' . $created . '</td>';
        echo '<td>';
        show_vendor_approval_form($vendorId);
        echo '</td></tr>';
    }
    echo '</tbody></table></div></section>';
    $result->free();
}
