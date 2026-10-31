# CnrByte Step 23 — Database Verification Preparation

## Scope

Step 23 continues from the Step 22 Settings Lookup Integration checkpoint. The focus is to prepare safe database verification for product hierarchy integrity, Settings lookup ownership/duplicates, HR record consistency, and the existence of expected schema columns.

## Work completed

- Added `STEP23_DATABASE_VERIFICATION.sql` to the project and provided a standalone copy.
- The SQL is read-only: it uses `SELECT` statements only and does not modify or delete data.
- The schema inventory checks expected columns for Settings, organisational ranks, products/category hierarchy, staff/HR/payroll, messaging, and notifications.
- Added diagnostics for duplicate/blank Settings values, duplicate rank definitions, invalid product hierarchy ancestry, products with inconsistent category paths, orphan qualifications, and HR records whose vendor does not match the referenced staff member.
- Added lookup owner/type coverage and index inventory queries to support later schema decisions.

## Static verification performed

- PHP syntax lint is run against all PHP files in the Step 22 checkpoint.
- ZIP archive integrity is checked after packaging.
- Static assertions verify that the Step 22 code and Step 23 diagnostics are present in the package.

## Not performed

No live MySQL/MariaDB connection or browser session is available in this work context. Therefore, the SQL has **not** been executed against the user's database, and no claim is made that the actual database passes these checks. Database results must be reviewed on a backup/staging copy.

## Safe execution order

1. Back up the database and use staging.
2. Select the intended database. Run section 1 first and review any `MISSING` columns.
3. If required tables/columns are missing, review and apply the relevant migration in the documented migration order before running the remaining sections.
4. Run sections 2–8 and save the result sets.
5. Resolve duplicates and orphan records with reviewed, reversible data fixes. Do not add unique indexes or delete/merge records until those results are understood.
6. Test Settings → dependent form → saved record for occupation, contract, qualification, leave, earnings and deductions; then test category creation and product persistence.
7. Record the test outcome before creating a production deployment package.

## Known remaining work

- Consolidate the two organisational-rank creation paths and duplicate lookup helper behavior after compatibility testing.
- Add safe edit/deactivate behavior for lookup values only after auditing references and existing data.
- Perform browser end-to-end testing for category creation, staff/HR/payroll, client/vendor messaging, permissions, checkout, refunds and settlement.
- Validate accounting and payroll workflows in the actual deployment environment; syntax checks do not establish accounting correctness or statutory payroll compliance.

## Current status

**Step 23 database verification is prepared but not executed against a live database.** The package remains a test checkpoint, not a production certification.
