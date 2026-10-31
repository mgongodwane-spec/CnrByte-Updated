# CnrByte Step 25 — Full Platform and Database Schema Audit

**Audit type:** Static source/schema-script audit of the Step 24 checkpoint.
**Audit date:** 2026-10-17.
**Scope:** All folders in the supplied project ZIP, PHP files, CSS/JS assets, local links/includes, SQL schema and migration scripts, authentication/session code, upload protection and previously documented module changes.

## Executive summary

The project is substantial and has broad coverage of marketplace, vendor management, client accounts, HR/payroll, messaging, banking, financial records, inventory, accounting, and administration. The latest Step 24 checkpoint contains 649 files (217 PHP, 21 CSS, 6 JavaScript, and 14 SQL files). All 217 PHP files passed `php -l`. Static checks found no missing literal local include paths and no missing literal local `href`/`src`/`action` targets in the scanned files. The project ZIP is a source snapshot, not a live environment.

**Overall status: NOT YET VERIFIED FOR PRODUCTION.** Syntax validity and static path checks do not establish correct runtime behavior, database compatibility, authorization coverage, or accounting correctness. No live database dump/connection, browser session, payment provider, or deployed web server was available during this audit.

## Confirmed findings and priorities

### P1 — Deployment configuration contains development defaults

`includes/conn.dbh.php` hardcodes `localhost`, database `cnrbyte`, database user `root`, and an empty password. This may be intentional for local XAMPP development, but it is unsafe as a production deployment pattern. The application should load credentials from environment/server configuration, use a least-privilege database account, and never expose connection details in public error output.

`includes/auth_helpers.php` also falls back to the literal pepper `CHANGE-ME-CNRBYTE-AUTH-PEPPER-IN-PRODUCTION` when `CNRBYTE_AUTH_PEPPER` is absent. That makes authentication throttle hashes predictable in any environment that has not set the variable. Production startup/deployment should fail closed when the pepper is missing or still equals the placeholder.

### P1 — Live schema and migration state are unknown

The package contains a base schema and staged migration/audit scripts, but no authoritative dump of the actual database currently in use. The SQL scripts define/alter tables over time, and `CREATE TABLE IF NOT EXISTS` does not retrofit columns/indexes into tables that already exist. The live schema may therefore differ from what the code expects.

The base schema file `includes/sql_tables/01_create_tables.sql` refers to `02_seed_data.sql`, but no file with that name is present in this checkpoint. Confirm whether seed data is intentionally omitted or the setup instructions are stale.

### P1 — Normalized-email uniqueness migration needs preflight data checks

`includes/sql_tables/09_step14_authentication_hardening.sql` adds a generated normalized email column and a unique key. If existing client records contain duplicate emails after `LOWER(TRIM(email))`, the unique-index migration can fail. Run duplicate detection on a backed-up staging copy before applying it. Do not assume the migration has already been applied to the active database.

### P1/P2 — SQL schema integrity is uneven

The schema scripts declare 75 `CREATE TABLE` statements across the SQL files. Several core ownership and hierarchy relationships rely on ordinary indexes and application-level checks rather than database foreign keys. Product hierarchy tables have indexes but their uniqueness constraints are commented out pending data checks. Other tables do use foreign keys, so integrity enforcement is not consistent across modules.

Before adding constraints, check existing data for orphaned vendor/branch/staff/product/category/account references, duplicate category paths, duplicate lookup values, and duplicate business identifiers. Do not run destructive cleanup automatically.

### P2 — Duplicate application helper definitions

Static scanning found 25 application-level function names declared in multiple non-vendor-library PHP files (excluding the bundled QR-code library). Examples include `get_types_by_owners_id`, `get_vendor_info_by_client_id`, `get_rank_info`, `get_rate_for_roster_row`, `show_selected_vendor_form`, `show_hierarchy`, `check_empty`, `upload_file`, and cart helper functions.

This can produce redeclaration fatals or behavior that depends on include order unless the files are mutually exclusive or guarded. Some duplicate helpers appear to be legacy copies. Consolidate shared functions into canonical modules and use `require_once` consistently after verifying call signatures and behavior.

### P2 — Dynamic SQL identifiers need whitelist review

The code contains prepared statements, which is positive, but prepared parameters cannot bind table/column identifiers. Several queries interpolate variables into SQL identifiers, for example dynamic `$column`, `$sum_colum`, and table names. Each identifier must be selected from a fixed allow-list before concatenation. The static scan cannot establish that every call site is safe, so these are manual-review targets rather than confirmed exploitable injections.

### P2 — Upload execution protection is partial by design

`uploads/.htaccess` disables CGI and denies common executable extensions, and `uploads/staff/qualifications/.htaccess` denies access. These are useful controls, but `.htaccess` behavior depends on Apache configuration and may not carry over to Nginx or another host. Review every upload handler for extension allow-lists, MIME/content validation, size limits, randomized names, path traversal resistance, and authorization. Consider storing uploads outside the web root and serving them through an authorized download handler for sensitive staff/identity documents.

### P2 — Role and tenant isolation require end-to-end validation

The code includes session, CSRF, vendor ownership and role checks in several newer handlers. However, legacy and newer code coexist, and a static scan cannot prove every read/write endpoint enforces the same tenant boundary. Test with at least two separate client accounts, two vendors, and an administrator to ensure IDs from another tenant cannot be used to view or modify records. Verify that pending/rejected vendors cannot use approved-vendor workflows.

### P2 — Financial/accounting correctness is not proven by schema presence

The project includes general-ledger, inventory, settlement, refund and financial reporting modules. This audit did not validate journal balancing, posting reversals, idempotency, period close behavior, tax treatment, valuation, or GAAP compliance against real transactions. These require test fixtures and accountant-reviewed expected outputs. Treat GAAP compliance as a requirement to validate, not a confirmed status.

## Static checks performed

- PHP lint: **217/217 PHP files passed** (`php -l`).
- Static local include-path scan: **0 missing literal include candidates**.
- Static local href/src/action scan: **0 missing literal path candidates**.
- Application helper scan: **25 duplicated function names across non-library application PHP files**.
- CSRF scan: POST/CSRF code is present in the project; static presence does not prove every state-changing endpoint is guarded or that all forms submit the right token.
- Upload directory protection and database connection/authentication configuration were inspected.
- SQL schema/migration scripts inventoried; no live schema comparison was possible.

## Folder/module coverage

| Area | Audit focus | Status |
|---|---|---|
| `admin/` | Vendor approval, category administration, lifecycle actions | Static review; runtime/authorization tests outstanding |
| `clients/` | Account/profile and client ownership | Static review; cross-tenant tests outstanding |
| `companies/` | Company data helpers | Static review; duplicate helper overlap identified |
| `css/`, `includes/css/`, `includes/scripts/` | Shared and module assets | File/path scan only; visual/responsive browser tests outstanding |
| `financial/` | Accounts, banking, statements and financial workflows | Static review; accounting assertions and database tests outstanding |
| `includes/` | Session/auth helpers, shared functions, accounting, SQL tables | Configuration, helper duplication, schema/migration risks identified |
| `marketplace/` | Product CRUD, category hierarchy, cart, quotations, QR flow | Static review; full transaction tests outstanding |
| `messages/` | Messaging/notifications | Static review; delivery, unread state and permission tests outstanding |
| `settings/` | Lookup administration and data flow | Prior Step 21/22 findings carried forward; staging validation outstanding |
| `signup_login/` | Signup, login, logout, password handling | Auth hardening code exists; migration/configuration and abuse tests outstanding |
| `vendors/` | Vendor, branch, shift, staff and HR/payroll | Static review; tenant-isolation and save/read tests outstanding |
| `uploads/` | User-generated files and access controls | Partial Apache rules present; host and upload-handler validation outstanding |
| `tools/`, bundled QR library | Utility/library code | Static syntax scan; dependency provenance/version review outstanding |

## Recommended remediation order

1. **Environment safety:** remove production reliance on root/blank database credentials; require a real authentication pepper; document local development configuration separately.
2. **Database preflight:** obtain a schema-only dump and run the supplied read-only diagnostics on a backup/staging database. Check duplicate normalized emails before the unique index.
3. **Schema reconciliation:** compare actual tables/columns/indexes against all application queries and migration scripts; create one ordered, versioned migration path.
4. **Tenant/authorization tests:** test every create/edit/delete/read workflow across client/vendor/admin roles and different tenants.
5. **Consolidate helpers:** resolve duplicate function declarations and conflicting legacy/new paths, then rerun lint and module tests.
6. **Data integrity:** after reviewing live data, add safe foreign keys/unique constraints and explicit transaction boundaries where justified.
7. **Marketplace and finance integration:** test product costs/prices, stock movement, cart/order lifecycle, refunds/settlements and balanced ledger entries with expected results.
8. **Browser QA:** test navigation, CSS loading, forms, uploads, messages and mobile responsiveness in a real browser.
9. **Production hardening:** review HTTPS, headers, logging, backups, least privilege, upload serving, error display, dependency licensing and deployment-specific server rules.

## Evidence and limitations

This report describes the supplied Step 24 source package. It does not claim the actual deployed database has any specific duplicate/orphan records, that a reported candidate is exploitable, or that any user-facing workflow has been exercised live. Those conclusions require the live schema, data diagnostics, and browser tests. The audit has not modified application PHP source files.
