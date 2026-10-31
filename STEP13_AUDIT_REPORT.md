# CnrByte Step 13 Static Audit Report

## Scope
Inspected the user-uploaded Step 12 ZIP and repaired missing bootstrap files,
empty module helper files, branch/shift/staff/HR form routing, shared CSS aliases,
selected internal links, and schema gaps discovered from existing PHP SQL.

## Implemented in this checkpoint
- Added `includes/sql_tables/08_step13_vendor_org_hr_schema.sql`.
- Added missing vendor compatibility columns (`user_id`, `status`, `is_active`,
  `verification_status`, `updated_at`) through idempotent information-schema
  checks and backfilled `user_id` from `client_id`.
- Added schemas for `qualifications`, `staff_leave`,
  `staff_earnings_deductions`, `business_types`, `vendor_types`,
  `organogram_ranks`, `income_expense_list`, `ppe_depreciation`, `companies`,
  legacy `cart`/`messages`, `cnrbyte_messages`, `cnrbyte_notifications`, and
  `marketplace_accounting_entries`.
- Added conditional columns referenced by `marketplace_orders.php` to orders
  and order items.
- Added vendor-scoped branch/shift/staff list helpers and forms, roster and
  leave forms, hourly rates, qualification records, and draft payroll items.
- Added ownership/CSRF checks in `vendors/module_action.run.php`.
- Added order-linked messaging and notifications.
- Standardised the shared header and added a responsive common CSS layer.
- Fixed broken internal paths and filled the empty PHP files found in the
  baseline.

## Static verification
- PHP syntax: **200/200 files passed**.
- Empty PHP files remaining: **0**.
- Missing local CSS links: **0 detected**.
- Missing static PHP include targets: **0 detected**.
- Missing absolute local href/src/action targets: **0 detected** after excluding
  the dynamically generated QR image filename.
- Missing relative form action targets: **0 detected**.

## Outstanding risks
1. SQL migration not executed against a live MySQL/MariaDB database.
2. Full system authorisation and CSRF review is not complete across every legacy
   controller; new branch/shift/staff/HR actions are protected, but older
   marketplace and financial handlers still need a separate security audit.
3. Payroll entries are drafts only; statutory South African payroll and GL
   posting are not implemented here.
4. Full financial statements, GAAP/IFRS framework selection, and consolidated
   branch accounting remain to be implemented and tested.
5. Messaging is currently order-linked; broader user-to-user conversations need
   a separate relationship/permission policy.
6. Refunds remain internal ledger credits, not external card/bank refunds.
   Settlement records do not execute payouts.
7. A browser-based responsive visual audit and database-backed functional tests
   remain outstanding.

## Recommended deployment sequence
Back up; apply SQL migrations on staging; verify existing vendor approval status;
run test cases for vendor separation and branch ownership; test messaging between
a client and a vendor sharing an order; test payroll drafts; then test checkout,
refund, settlement, and ledger reconciliation. Only promote after those tests
pass.
