# CnrByte Step 18 — Vendor Approval Form and Settings Audit

## Baseline
Built from the Step 17 full-module security audit checkpoint. No database migration was added.

## Vendor approval workflow
- Added the pending vendor approval queue to the administrator dashboard.
- Added a reusable approval form renderer to `includes/show/global_forms.shw.php`, using the existing shared CSRF field helper.
- The form posts the vendor ID to `admin/approve_vendor.run.php` and requires confirmation before submission.
- Updated the dashboard renderer to show escaped vendor registration/account information and an empty-queue state.
- Hardened the approval endpoint to enforce POST + CSRF, canonical administrator roles and session ID fallbacks.
- Updated approval processing to use a transaction, lock the pending vendor row, update only a still-pending vendor, avoid changing an administrator account's role, and send the approval notification as part of the transaction.
- Added a dashboard result message for successful, failed, and invalid approval attempts.

## Settings audit and fixes
- Added a login guard to `settings/settings.pge.php` and canonicalized administrator role handling.
- Replaced navigation forms that posted to dashboard pages with ordinary links; those navigation actions do not mutate data and should not be POST submissions.
- Normalized the settings layout and form controls for responsive/mobile use.
- Validated the selected lookup category against an allowlist, trimmed and length-checked names, and prevented duplicate lookup values for the current account.
- Stopped trusting the hidden `client_id`, `current_dashboard`, and `selected_pge` values for settings writes.
- Restricted organisational-rank creation to an approved active vendor workspace, verified vendor ownership server-side, validated hierarchy level and pay rate, and saved the vendor ID and owner ID to the rank record.
- Added a vendor selector to the administrator product/QR form instead of relying on an often-empty hidden selected-vendor ID. The list is limited to approved vendors.
- Hardened administrator product creation with server-side category/product validation, detected MIME and 5 MB upload limits, an approved-vendor check, correct vendor-owner IDs for categories, transactional inserts, and cleanup of image/QR artifacts if the transaction fails.
- Escaped lookup option values and one-time settings/global flash messages to reduce HTML injection risk.
- Replaced the blank settings display helper with a safe flash-message renderer and guarded the settings lookup helper against duplicate function declarations.

## Verification
- PHP syntax: 216/216 files passed.
- Focused assertions: 20/20 passed.
- Static include/require target scan: 0 missing targets.
- Static local href/src/form-action scan: 394 references checked, 0 missing targets.
- ZIP integrity: checked after packaging.

## Limitations and follow-up
- No live MySQL/MariaDB connection was available; SQL queries, transaction rollback behavior, and notification insertion were not executed against a staging database.
- No browser was available; administrator approval and settings forms were not interactively exercised.
- Run this checkpoint against a staging database before production. Verify that the Step 13 vendor schema migration is applied and test approving a pending vendor, repeated approval attempts, missing/invalid CSRF tokens, vendor ownership, rank creation, image upload failure, and QR-generation failure.
- The administrator dashboard configuration form still stores an `admin_objs` record; this change does not establish that those configuration rows dynamically alter the dashboard UI.
- This is a static code audit, not a full penetration test or production-readiness certification.
