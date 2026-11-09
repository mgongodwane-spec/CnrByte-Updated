# CnrByte Security and Data-Integrity Audit Report

**Report date:** 9 November 2026\
**Project:** CnrByte\
**Audit type:** Read-only source-code, schema, migration, and
test-database inspection\
**Repository under review:** `~/CnrByte-repo`\
**Status:** Inspection findings consolidated; no repository changes made

------------------------------------------------------------------------

## 1. Executive summary

The inspection identified several confirmed application-level isolation
and integrity weaknesses, alongside controls that are working in the
inspected code paths.

The most important confirmed issue is **stale session-record leakage
across vendor/workspace changes**. Product and cost-item records
retained in session state are not consistently invalidated when the
active vendor changes. The corresponding forms can therefore render
records selected under a previous vendor. The inspected product and
cost-item mutation handlers independently verify vendor ownership before
writing, so this inspection did **not** establish a cross-vendor
database modification through those handlers.

Other material findings include:

-   Product hierarchy bootstrap performs DDL and legacy-data
    synchronization during ordinary page execution.
-   Product hierarchy helper functions can insert categories directly
    and use SELECT-then-INSERT patterns without demonstrated uniqueness
    protection.
-   Product-category validation in one edit path redirects on an invalid
    path but does not immediately terminate execution.
-   Stock-purchase journal creation trusts the branch ID held in session
    without independently verifying that the branch belongs to the
    active vendor.
-   Confidential vendor and financial documents are stored beneath the
    general uploads tree without a demonstrated authenticated download
    boundary.
-   Several upload workflows can leave orphaned files when later
    database operations fail.
-   Migration 15 and Migration 16 have operational/idempotency concerns
    and require controlled deployment planning.
-   Legacy messaging helpers contain insufficiently scoped queries but
    were not found to have active call sites.

The audit also verified meaningful safeguards: active product and
cost-item writes use vendor scoping; active direct-client messaging
validates contacts; order messaging resolves orders against the
authenticated buyer or vendor; and vendor direct chat checks vendor
ownership and approval.

**No files, database records, migrations, Git history, or remote
repositories were changed as part of these inspections.**

## 2. Scope and methodology

The review covered the PHP source and SQL files available in the local
CnrByte checkpoint, selected session and authorization flows, messaging
flows, product and cost-item workflows, vendor/branch/HR integrity,
financial schemas, upload handling, and selected isolated test
databases.

Inspection methods included:

-   Read-only `grep`, `sed`, and source review of PHP and SQL.
-   Schema and migration review.
-   Inspection of session assignment, invalidation, and consumption
    points.
-   Review of authorization predicates and tenant-scoped SQL.
-   Review of existing test-database migration checks and transaction
    tests.
-   Searches for call sites and include references for legacy helpers.

This report distinguishes confirmed defects from missing defensive
controls, deployment-dependent exposure, and issues requiring policy or
production-schema verification.

### Limitations

-   This was not a full penetration test or independent security
    certification.
-   The production `cnrbyte` database inspected locally contained no
    core business fixtures. Test databases were used for controlled
    migration checks; their data is not evidence of production data.
-   Local source and migration files do not establish the exact live
    production schema or web-server configuration.
-   No live deployment, external hosting configuration, or production
    traffic was tested.
-   Dormant code is not treated as an active exploit path without
    evidence of invocation.

## 3. Findings by severity and confidence

Severity is a prioritization assessment based on the inspected evidence,
not a claim that exploitation has occurred.

### High priority

#### F-01 --- Stale product and cost-item session records can cross vendor context

**Status:** Confirmed\
**Impact:** Cross-vendor record disclosure in form rendering; no
cross-vendor write demonstrated in the inspected handlers.

Product and cost-item records are stored in session state and consumed
by edit/sell/stock forms. Vendor selection clears branch, shift, and
staff session state, but does not consistently clear product, cost-item,
contact, and selected-form state. The form functions check that session
values exist but do not consistently revalidate that the referenced
record belongs to the active vendor before rendering its details.

This permits a stale product or cost-item record selected under one
vendor context to remain available for display after switching to
another vendor context.

The inspected mutation handlers provide a separate ownership check: -
Product operations check product ID against the active vendor. - Product
updates use vendor-scoped `WHERE` predicates. - Cost-item edit reloads
the record and compares its vendor ID with the active vendor. -
Cost-item updates use vendor-scoped predicates. - Stock purchase locks
the cost item using both item ID and active vendor ID.

Therefore, the confirmed issue is stale-context form display. The
reviewed handlers did not demonstrate that this state can be used to
modify another vendor's product or cost item.

**Recommended remediation:** 1. Invalidate product, cost-item, contact,
and selected-form state on vendor/workspace changes and on invalid
vendor context. 2. Re-fetch each record from the database using both
record ID and active vendor ID before rendering a form. 3. Avoid
treating session-cached database rows as authorization evidence. 4. Add
regression tests that select a record under vendor A, switch to vendor
B, and verify that the old record is neither rendered nor actionable.

#### F-02 --- Confidential documents lack a demonstrated private-storage and authorized-download boundary

**Status:** Confirmed storage design concern; actual public exposure is
deployment-dependent.

Staff qualification uploads have an `.htaccess` rule denying access. The
general uploads tree disables CGI and blocks script-like extensions, but
ordinary PDF and image files remain allowed. Other document directories,
including official documents, banking documents, and vendor profile
assets, did not show equivalent dedicated access rules in the inspected
source.

Vendor certificates are stored under `uploads/vendors/profile`,
alongside profile assets. Official and banking documents are also stored
under the uploads tree. No authenticated, authorization-checked download
handler was identified in the inspected code.

Whether a file is directly retrievable depends on the deployed web
server, directory configuration, and access rules. The inspection does
not prove that a particular live document is publicly accessible.

**Recommended remediation:** 1. Move confidential documents outside the
web root. 2. Provide a download controller that authenticates the
requester and checks ownership/role/record-level authorization. 3. Keep
public profile images in a separate public directory. 4. Add deployment
tests that attempt direct URL access to private documents and require
denial. 5. Review existing stored files and access logs before deciding
whether incident response is required.

### Medium priority

#### F-03 --- Product hierarchy bootstrap performs schema and data work during page rendering

**Status:** Confirmed.

`product_hierarchy_bootstrap()` ensures tables, synchronizes legacy
categories, and synchronizes product category paths. The bootstrap is
invoked from ordinary page execution, including the marketplace
dashboard and dropdown helpers.

The table setup uses `CREATE TABLE IF NOT EXISTS`. Runtime
synchronization can insert categories and update product hierarchy
references. This means normal page rendering can trigger DDL and data
mutation.

Additional weaknesses observed: - Some helper reads return an empty
array on prepare failure, masking database errors. - Category creation
uses SELECT-then-INSERT patterns without demonstrated unique constraints
for all relevant category scopes. - Concurrent requests can race and
create duplicate category rows where schema constraints do not prevent
them. - Helpers can insert live categories directly, bypassing the
proposed category approval workflow. - Legacy synchronization silently
skips some unresolved parent relationships.

**Recommended remediation:** 1. Move schema creation and data migration
out of request-time code. 2. Run explicit versioned
migrations/deployment commands. 3. Make runtime helpers read-only. 4.
Add appropriate unique constraints and handle duplicate-key races. 5.
Fail visibly and log database errors rather than converting prepare
failures into empty result sets. 6. Define one authoritative
category-creation workflow.

#### F-04 --- Invalid product-category path does not immediately stop the edit handler

**Status:** Confirmed control-flow defect.

In the product detail edit path, an invalid hierarchy path causes an
error to be recorded and redirect logic to be called, but execution is
not immediately terminated. The handler may continue into subsequent
update logic.

**Recommended remediation:** - Terminate the request immediately after
the redirect/error response. - Structure validation as a hard
precondition before any write. - Add tests proving invalid category
paths cannot reach the update statement.

#### F-05 --- Stock-purchase journal creation does not independently validate branch/vendor pairing

**Status:** Confirmed missing defensive validation; normal-flow exploit
not demonstrated.

The stock-purchase handler obtains the active branch ID from session
state and uses it in journal insertion. The cost item is locked and
validated against the active vendor, but the handler does not
independently query the branch using both branch ID and active vendor ID
before writing the journal entry.

The inspected journal schema permits nullable branch IDs and does not
enforce a composite relationship proving that a branch belongs to the
same vendor recorded on the journal row. The normal branch-selection
flow is vendor-scoped, and changing vendors clears branch state. No
normal-workflow cross-vendor branch exploit was demonstrated.

**Recommended remediation:** 1. Before starting or committing the
stock-purchase transaction, verify the branch with
`SELECT id FROM branches WHERE id=? AND vendor_id=?`. 2. Reject a
missing or mismatched branch. 3. Decide explicitly whether branchless
journal entries are valid; enforce that policy consistently. 4. Where
feasible, add a composite foreign key using a unique `(id, vendor_id)`
key on branches and matching columns on journal tables. 5. Add negative
tests for a branch belonging to another vendor.

#### F-06 --- Upload workflows can leave orphaned files

**Status:** Confirmed in several inspected paths.

Multiple upload handlers move files before all later database operations
have succeeded. If validation, duplicate detection, or database
insertion/update fails after the move, the file may remain without a
corresponding database record.

Examples observed: - Vendor registration certificate upload can remain
after vendor insert failure. - Product creation can leave an image after
database insertion failure. - Product edit can move a replacement image
before category validation or database update. - Some banking and
official-document paths do not consistently remove uploaded files after
failure. - Trust-application upload flow showed stronger
transaction/rollback cleanup than some regular banking paths.

**Recommended remediation:** 1. Stage uploads in a temporary/private
directory. 2. Complete validation and database work before promoting the
file to its final location, or use compensating cleanup on every failure
path. 3. Use database transactions where appropriate, while recognizing
filesystem operations are not transactional. 4. Add cleanup jobs for
stale unreferenced files. 5. Check all database execution results before
reporting success.

#### F-07 --- Login/workspace transitions do not clear all record-specific session state

**Status:** Confirmed.

Successful login regenerates the session ID and rotates the CSRF token,
but the inspected login/session helper does not clear every existing
session key. The helper replaces user identity values and unsets the
active vendor, but branch, shift, staff, product, cost-item, contact,
and selected-form values can remain.

Logout and password-reset paths clear session state more
comprehensively. Vendor selection and invalid-vendor handling clear
workspace keys but do not consistently clear record-specific keys.

**Recommended remediation:** 1. Establish a central session-context
reset function. 2. Clear all user- and workspace-specific cached records
at authentication identity changes. 3. Clear dependent state whenever
vendor, branch, or selected workflow changes. 4. Prefer storing stable
identifiers in session and reloading authorized records from the
database.

#### F-08 --- Migration 16 category-submission ownership constraints are incomplete

**Status:** Confirmed schema design gap in the migration file; migration
not shown as applied.

Migration 16 creates vendor category submissions with separate
references to vendor and submitting client. The schema does not prove at
the database level that the submitting client owns the referenced
vendor. The migration also contains other integrity gaps: - Reviewer IDs
do not have a demonstrated client foreign key or database-level
admin-role constraint. - Review-history actor IDs are optional and do
not have a client foreign key. - Some polymorphic parent references and
live-category IDs require application-level validation. - Name
reservation status is not constrained to match submission status. - Live
category references are not fully constrained to the expected
path/parent.

No PHP references to the Migration 16 workflow tables were found in the
inspected source, so the proposed workflow was not demonstrated as
implemented.

**Recommended remediation:** 1. Add a unique `(id, client_id)` key to
vendors where appropriate. 2. Use a composite foreign key from
submission `(vendor_id, submitted_by_client_id)` to vendor
`(id, client_id)`. 3. Add appropriate actor/reviewer foreign keys and
application role checks. 4. Validate polymorphic parent and
live-category relationships transactionally. 5. Implement and test the
PHP workflow before applying the migration.

#### F-09 --- Migration deployment scripts have failure-recovery and idempotency risks

**Status:** Confirmed in inspected migration files.

Migration 15 and Migration 16 use DDL and stored-procedure preflight
logic. If a preflight procedure signals an error, cleanup statements
later in the script may not execute. DDL auto-commit behavior means a
later failure can leave earlier schema changes in place. No migration
ledger was identified in the inspected workflow, and the scripts are not
fully idempotent.

Migration 15 was tested in an isolated database. Migration 16 was also
inspected/tested in an isolated database. Neither test result proves
that the migrations have been applied to production.

**Recommended remediation:** 1. Use a migration runner with a durable
migration ledger. 2. Make each migration's preflight, DDL, and recovery
strategy explicit. 3. Test both clean installation and
interrupted/partial deployment recovery. 4. Take and verify backups
before production DDL. 5. Do not assume DDL can be rolled back as one
transaction.

### Lower priority / policy-dependent

#### F-10 --- Legacy messaging helpers are insufficiently scoped but appear dormant

**Status:** Dormant code confirmed; active exposure not demonstrated.

The legacy `get_contact_info_by_client_id()` and `load_chat()` helpers
are defined in `includes/global.dbhhhhh.php` but no call sites were
found. Functions in `messages/messages.shw.php`, including
`show_messages_table()`, `create_message_form()`, and
`edit_message_form()`, were also found only at their definitions, with
no include or call references in the inspected PHP source.

The legacy message-table helper contains an unscoped query that would be
unsafe if invoked in a user-facing context. However, because no active
call path was found, it is not classified as a demonstrated live
vulnerability.

**Recommended remediation:** - Remove the obsolete code or bring it
under the same authorization and tenant-scoping rules as active
messaging. - Add automated searches/tests to prevent accidental
reintroduction of the legacy include path.

#### F-11 --- Order messaging does not visibly enforce vendor approval status

**Status:** Confirmed behavior in inspected paths; policy-dependent.

The order messaging dashboard and handler check ownership/order
association but do not visibly require the vendor to be approved. A
pending or rejected vendor owner who has relevant orders may therefore
be able to access or send order-related messages through those paths.

By contrast, the dedicated vendor direct-chat pages and handlers use
`vendor_guard.php`, which checks ownership and `status='approved'`.

**Recommended remediation:** - Confirm the business rule for
pending/rejected vendors. - If approval is required for all vendor
messaging, apply the same guard consistently to the order messaging
dashboard and handler. - Add tests for pending, rejected, and approved
vendor states.

#### F-12 --- Financial schema integrity and data-retention concerns

**Status:** Confirmed in original schema; live-schema status unverified.

The original schema defines banking applications, financial accounts,
and trust applications with `client_id NOT NULL` but without foreign
keys to clients. Financial transactions reference financial accounts
with `ON DELETE CASCADE`, which can remove transaction history when an
account is deleted. Trust beneficiaries reference trust applications and
trust members independently, without a composite constraint proving both
belong to the same trust.

No later migration altering these relationships was found in the
inspected migration set. Because `CREATE TABLE IF NOT EXISTS` does not
retrofit an existing table, the original schema file alone cannot
establish the live database structure.

**Recommended remediation:** 1. Inspect the actual deployed schema
before planning changes. 2. Add client foreign keys and same-trust
composite constraints where compatible with existing data. 3. Review
whether financial transaction history must be retained; consider
restricting account deletion or using soft deletion. 4. Validate
historical data before applying constraints.

#### F-13 --- HR validation gaps and ownership consistency concerns

**Status:** Confirmed in inspected code/schema paths; deployment and
policy impact varies.

Migration 15's isolated tests demonstrated that invalid
cross-tenant/branch relationships covered by its foreign keys are
rejected. The PHP HR paths nevertheless showed validation gaps: - Equal
shift start and end times are not rejected. - Lunch intervals are not
consistently checked against shift boundaries or duration. - Day name is
accepted independently of the supplied date's weekday. - Some helper
functions accept a client ID but do not use it. - Vendor ownership
helpers use differing `client_id` / `user_id` logic. - Qualification
uploads can be orphaned, and the qualification schema lacks
vendor/branch ownership columns or a demonstrated FK.

**Recommended remediation:** 1. Normalize vendor ownership resolution
and document the canonical owner field. 2. Validate shift and lunch
intervals as a single coherent time model. 3. Derive weekday from the
date rather than trusting two independent inputs. 4. Scope qualification
records and downloads to the relevant tenant. 5. Add tests for boundary
times, invalid dates, and ownership edge cases.

## 4. Controls verified in inspected active paths

The following controls were present in the reviewed code:

-   Product and cost-item mutation handlers perform vendor ownership
    checks before writes.
-   Product and cost-item updates include vendor ID in their update
    predicates.
-   Stock-purchase cost-item lookup/locking is scoped by item ID and
    vendor ID.
-   Active direct-client messaging checks that the receiver is a saved
    contact of the authenticated sender.
-   The active chat page resolves contacts and conversation access
    against the authenticated client.
-   Order messaging resolves the selected order against the
    authenticated buyer or active vendor.
-   Dedicated vendor direct messaging checks vendor ownership and
    approved status.
-   Vendor direct-chat customer selection is constrained to customers
    with orders for that vendor.
-   The normal branch-selection handler fetches branches using both
    branch ID and active vendor ID.
-   Session bootstrap enables strict session mode, cookie-only sessions,
    HttpOnly, SameSite=Lax, and secure cookies when HTTPS is detected.
-   Login regenerates the session ID and rotates the CSRF token.
-   Migration 15's isolated test cases rejected the cross-tenant/branch
    relationships covered by its foreign keys.

These controls reduce risk in the specific paths inspected. They do not
replace end-to-end testing or establish that every route follows the
same rules.

## 5. Migration 15 isolated test results

The isolated Migration 15 test database used MariaDB 12.3.2 and PHP
8.5.1.

Observed results: - Valid branch, shift, staff, roster, leave, and
payroll relationships were accepted. - Invalid cross-branch/vendor
relationships covered by the migration's foreign keys were rejected with
FK error 1452. - Optional HR relationships behaved as expected in the
tested cases. - Parent deletion was restricted in the tested
relationship cases. - PHP integer parameter binding of `NULL` was
verified to send SQL `NULL`.

Limitations: - The fixture set included one vendor, so it was not a full
multi-tenant concurrency or adversarial test suite. - These results
apply only to the isolated test database and tested cases. - Migration
15 was not established as applied to production.

## 6. Environment and repository state

The inspected local environment showed: - Repository path:
`~/CnrByte-repo`. - MariaDB was restarted after a device restart and
responded to `mysqladmin ping`. - Production-named local database
`cnrbyte` contained zero rows in the inspected core entity tables
(vendors, profiles, clients, branches, shifts, staff, contacts, and
products). - Separate test databases existed for Migration 15, Migration
16, and regression checks. - Test fixtures must not be copied into
production as a shortcut or replacement dataset.

A previously inspected Git checkpoint contained: - 30 tracked files
modified. - 10 untracked files. - Existing checkpoint work that must be
preserved. - A CRLF warning associated with `includes/conn.dbh.php`;
this should not be normalized or rewritten casually.

The modified/untracked files are part of the existing working
checkpoint. This audit did not alter them. Before any future
implementation or repository publication, inspect `git status`, review
diffs, and confirm the intended commit boundary.

## 7. Prioritized remediation roadmap

Recommended sequence:

1.  **Session and tenant isolation**
    -   Centralize session invalidation.
    -   Revalidate record ownership before rendering forms.
    -   Add vendor-switch regression tests.
2.  **Private document handling**
    -   Move confidential files outside the public web root.
    -   Implement authenticated, authorized downloads.
    -   Verify direct URL denial in the deployed environment.
3.  **Write-path integrity**
    -   Fix product-category validation termination.
    -   Validate branch/vendor pairing in stock-purchase handling.
    -   Add transaction and failure-path tests.
4.  **Runtime architecture**
    -   Remove DDL/data synchronization from page rendering.
    -   Make migrations explicit and recoverable.
    -   Add uniqueness constraints and visible error handling.
5.  **Upload lifecycle**
    -   Add staged upload handling and compensating cleanup.
    -   Audit orphan files and references.
6.  **Migration and schema integrity**
    -   Close Migration 16 submitter/vendor ownership gap.
    -   Review financial and HR constraints against the actual deployed
        schema.
    -   Establish a migration ledger and recovery procedures.
7.  **Messaging and approval policy**
    -   Remove or secure dormant legacy messaging helpers.
    -   Decide whether pending/rejected vendors may use order messaging
        and enforce that decision consistently.
8.  **Regression and release assurance**
    -   Add multi-vendor tests for read, write, messaging, branch,
        upload, and session transitions.
    -   Run syntax checks and the full test suite.
    -   Review all diffs and deployment scripts before release.

## 8. Release gate

Do not publish or deploy this checkpoint until: - The existing working
changes and untracked files have been inventoried and backed up. - The
user has reviewed the proposed remediation scope. - Critical
session-isolation and private-document controls are implemented and
tested. - Migration deployment and recovery procedures are validated. -
Tests cover at least two vendors and negative cross-tenant cases. - The
final Git diff and commit contents are reviewed. - The user explicitly
approves the target repository and push.

The current repository destination preference recorded in the project
context is a **new public repository named `CnrByte-Updated`**, leaving
the existing `CnrByte` repository intact. Confirm that destination
immediately before creating or pushing anything because earlier
repository instructions changed during planning.

------------------------------------------------------------------------

## 9. Audit conclusion

The inspection found real issues that should be addressed before
release, particularly stale session-record rendering across vendor
changes, confidential-document storage boundaries, request-time
schema/data mutation, and incomplete defensive validation in selected
write paths.

The inspection also found working tenant checks in several important
mutation and messaging paths. No cross-vendor product/cost-item database
write, active legacy-messaging exploit, or normal-workflow cross-vendor
branch exploit was demonstrated in the reviewed evidence.

This report consolidates the findings available from the read-only
inspection performed through 9 November 2026. It is an engineering audit
record, not a claim that remediation has been completed.
