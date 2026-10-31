# CnrByte Step 17 — Full Static Module Security Audit

## Baseline
- Built from Step 16 vendor account/profile/messaging checkpoint.
- No database schema migration was added in Step 17.
- Scope: cross-module static review of authentication/session use, state-changing handlers, client/vendor ownership checks, file uploads, category administration, financial application status controls, marketplace cart/checkout, QR packaging, local links and PHP includes.

## Changes
1. Added a shared strict POST + CSRF request guard in `includes/auth_helpers.php` and applied it to account settings, product create/edit, financial account create/edit, banking application create/edit, statements creation, cart mutations/checkout, QR packaging, and vendor approval.
2. Added CSRF tokens to the corresponding forms, including product, financial, banking, cart, deposit, packaging and settings forms.
3. Added CSRF validation to administrator product-category insertion and added CSRF fields to all four category forms. Admin access checks use the canonical session role with legacy fallback.
4. Restricted product creation/editing and QR packaging to the selected vendor workspace. QR package listing, order lookup and final update now include selected `vendor_id` as well as the account owner ID.
5. Hardened shared banking/address/document upload helpers with `is_uploaded_file`, size limits, extension allowlists, detected MIME checks, secure random filenames, anchored upload paths and restricted directory permissions.
6. Hardened banking application document upload to allow only matching PDF/PNG/JPEG files up to 10 MB. Corrected trust-application `bind_param` type ordering.
7. Prevented applicants from setting their own banking/trust application status to `approved` or `declined`. The client UI now lists application statuses read-only and explains that an administrator controls decisions.
8. Fixed the legacy income/expense statement handler's impossible expense condition, added a selected vendor/branch ownership check, and validated that the selected income/expense type belongs to the signed-in client.
9. Hardened the administrator product image upload and stopped returning raw exception text in redirect URLs.
10. Corrected four broken internal navigation links: vendor business type settings, signup Home link, QR-code settings return link and quotation marketplace return link.
11. Disabled unused legacy endpoints that either exposed raw SQL text or provided obsolete/unsecured mutation paths: legacy product upload, message edit, statement edit and vendor SQL-serving endpoint. They now return HTTP 410 with a plain-text explanation.
12. Added an Apache `.htaccess` rule under `uploads/` to deny direct execution/access of common server-side script extensions.

## Static verification
- PHP files linted: **216/216 passed**.
- Empty PHP files: **0**.
- Static local href/src/action references missing: **0**.
- Static include/require targets missing: **0**.
- CSRF-guarded handlers found by the targeted static scan: **19**.
- Additional state-changing handlers with POST/CSRF indicators: **27**.
- Focused static assertions: **42/42 passed**.
- CSRF helper smoke test: passed for valid and invalid tokens.
- ZIP integrity: verified after packaging.

These results are static checks. They do not prove that every workflow, SQL statement, migration or business rule works against a live database.

## Staging test plan
1. Use a copied project folder and a disposable copy of the database; keep production unchanged.
2. Confirm the Step 13–16 migrations are applied in order before testing the Step 16 baseline. Step 17 itself adds no SQL migration.
3. Sign in as a client, a vendor owner, a vendor account with multiple vendor workspaces, and an administrator. Verify unauthorized role access is denied.
4. Test cart add/update/remove, address confirmation, terminal/card internal-balance checkout, deposit notification and vendor package/QR actions. Verify invalid or missing CSRF tokens fail with 403/405.
5. Test vendor A cannot edit vendor B's products, branches, shifts, staff, messages or order package even if IDs are changed in the request.
6. Test banking/trust application creation with valid and mismatched MIME/extension uploads, oversized files, empty files and non-upload paths. Verify applicant cannot approve or decline their own application.
7. Test category creation and settings writes with CSRF tokens and with tampered `client_id` values.
8. Confirm upload directories cannot execute uploaded PHP/PHTML/PHAR files on Apache. Apply equivalent deny rules if deploying on Nginx or another server.
9. Test every navigation route on desktop and mobile and check browser console/server logs.
10. Verify financial statements, checkout ledger effects, inventory decrements, notifications and messaging records in the staging database after each operation.

## Important limitations and open items
- No MySQL/MariaDB server or credentials were available here; migrations and database transactions could not be executed in staging.
- No browser/HTTP end-to-end environment was available; checkout, upload, notifications, QR generation and messaging were not exercised interactively.
- The existing payment flow appears to use internal account balances; this audit does not establish that an external card payment gateway exists or is compliant.
- Statutory South African payroll, GAAP/IFRS reporting, and a full double-entry general ledger still require a separate business/accounting audit and live reconciliation tests.
- The admin vendor-approval handler exists, but no active form targeting it was found in the current static UI scan; the approval workflow should be reviewed before production use.
- `uploads/.htaccess` applies to Apache. Nginx and other servers need equivalent server configuration.
- Static route scans cover literal local paths only; generated URLs and runtime redirects still need browser testing.
- This is not a full penetration test or a production-readiness certification.
