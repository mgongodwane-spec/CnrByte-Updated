# CnrByte Step 19 — Wide Code, SQL Schema, and Forms Audit

## Scope and method

This checkpoint is based on Step 18. The review covered the PHP application, SQL migrations, SQL statements, prepared-statement bindings, form actions and CSRF fields, local links/assets, account/workspace ownership checks, settings, quotation creation, vendor approval, QR maintenance utilities, financial statements, and the newer vendor HR action handler.

This was a static source audit. No MySQL/MariaDB server, live credentials, deployed browser session, or SMTP service was available, so the migration and workflows still require staging execution.

## Repairs

### Authentication and workspace routing
- Canonical role normalization now preserves vendor/seller/merchant roles instead of silently converting them to client.
- Post-login routing now sets `current_dashboard` consistently for admin, vendor-selection, vendor, and client workspaces. This prevents shared forms from treating a vendor session as a client workspace.

### Database schema and SQL statements
- Added `status` to the base `products` schema and an upgrade-safe Step 19 migration that normalizes older product status columns to `VARCHAR(40)`. The admin product form wrote `products.status`, but the base schema did not define that column.
- Added indexes used by quotation history and branch-scoped statement duplicate checks.
- Reworked financial statement creation to validate account-type ownership, permitted account types, dates, amounts, rates, and share types before writing.
- Income/expense and fixed-asset/liability journal rows now use correct `bind_param` types for DECIMAL and VARCHAR columns.
- Fixed the invalid postal-address bind type string (`sssss i` → `sssssi`).
- Fixed fixed-asset/liability writes so duplicate checks occur before any journal write and both related rows commit or roll back together.
- Duplicate checks now include vendor and branch scope, preventing unrelated branches from blocking one another.
- Cost-of-sale linking now rejects product or cost-item IDs owned by a different vendor.
- Trust application inserts now bind the correct number and types of values, associate each beneficiary with the actual inserted member ID instead of hard-coded member ID `1`, validate arrays and allocations, and roll back related records on failure.

### Quotation forms and SQL
- Corrected the product lookup to use `products.sale_price` and the actual category hierarchy tables instead of nonexistent `products.price`, `products.category_id`, and `categories.category_name` fields.
- Quotation creation now requires an authenticated client and a valid CSRF token.
- Product prices and totals are recalculated from the database on the server; posted browser prices are not trusted.
- Product lookup is limited to approved vendors and quotation creation uses a transaction.

### Forms, CSRF, and routes
- Added missing CSRF inputs to legacy client vendor/profile forms and corrected their form actions.
- Fixed a contact-selection form that emitted a literal PHP tag inside an `echo` string, leaving the actual CSRF input absent; contact names are escaped before output.
- Corrected a malformed extra closing `</select>` in legacy vendor forms and corrected a business-type helper call that passed a string where an integer account ID was required.
- Updated statement form input types, required fields, numeric limits, and field lengths to match server validation.
- The obsolete HR `create.run.php` and `edit.run.php` endpoints have been retired with HTTP 410; current HR forms use `vendors/module_action.run.php`, which performs ownership checks.
- QR sync and bulk-generation utilities no longer mutate data on a GET request. Both are admin-only and require POST plus CSRF for execution. The global QR Shop navigation no longer points to a bulk database mutation utility.
- QR generation validates the product ID, uses restrictive directory permissions, and validates or uses `CNRBYTE_BASE_URL` when constructing scan links.
- The shared global forms handler now has a safe default upload filename prefix for client workspaces.

### Vendor HR form/action checks
- Added branch-code and shift-name duplicate checks on updates.
- Added field-length and numeric validation for staff, hourly rates, payroll items, and roster inputs.
- Validated roster dates/times and category values, added duplicate roster checks, and retained vendor/branch/shift ownership validation.
- Strengthened qualification-upload checks using upload status, size, extension, and detected MIME type.
- Validated leave types/date ranges and capped reason length.

## Static verification

- PHP syntax: 216/216 PHP files pass `php -l`.
- `bind_param` signature checks: 454 calls checked; no type-string/argument-count mismatches detected.
- Static SQL/schema column scan: zero unresolved column references in recognized literal INSERT/UPDATE/qualified-column patterns.
- Local links/assets/form actions: 409 reference candidates scanned; 11 were dynamic PHP/template expressions, and zero missing literal targets were found (398 literal targets checked).
- Form scan: 131 forms found, including 103 POST forms; no missing-CSRF candidates remained after accounting for helper-generated token fields. The third-party QR demo form is not a CnrByte state-changing endpoint.
- ZIP integrity is checked after packaging.

## Apply migration on staging

1. Back up the database.
2. Apply `includes/sql_tables/01_create_tables.sql` to a fresh staging database if the base schema has not been created.
3. Apply the included migrations in numeric order, including `12_step19_schema_integrity.sql`, after the earlier migrations they depend on.
4. Review the SQL for your database/version and confirm the selected schema name is `cnrbyte` before execution.
5. Test admin product creation, quotation creation, trust applications with multiple members and beneficiaries, income/expense statements, fixed assets/liabilities, shares, vendor approval, QR sync, vendor HR rosters, and uploads.

## Remaining limitations / production checks

- No live SQL migration or database integration test was run. Static column checks cannot prove the deployed database matches these migrations or catch every dynamic SQL/runtime issue.
- No browser end-to-end tests were available.
- Sensitive uploaded banking/official documents are still stored beneath the web root. The current `.htaccess` blocks executable script extensions, but does not by itself prevent direct reading of private documents. A controlled download route and deployment-specific Apache/Nginx rules remain necessary before production. Set `CNRBYTE_BASE_URL` to the public origin (for example, `https://example.com`) when generating QR links in a deployment where the HTTP Host header should not be trusted.
- Full GAAP/IFRS reporting, reconciled double-entry accounting, South African statutory payroll, and production security/penetration testing remain separate workstreams.
