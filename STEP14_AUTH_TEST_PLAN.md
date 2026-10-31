# CnrByte Step 14 — Authentication Verification Plan

## Static checks run in this checkpoint
- PHP syntax validation for all PHP files in the archive.
- Assertions that signup assigns only `client`, validates CSRF and confirmation, and contains no email-based admin rule.
- Assertions that login validates CSRF, verifies password hashes, rotates session ID/CSRF, applies throttling, and routes by trusted database role/vendor ownership.
- Assertions that logout is POST-only and validates CSRF.
- Assertions that reset tokens are random, stored as SHA-256 hashes, expire, and can be consumed only once.
- Assertions that vendor selection validates both CSRF and the authenticated user's vendor ownership.
- Assertions that the admin dashboard checks the admin rank.
- Static path scan for the known misspelled login route.
- ZIP integrity verification.

## Staging functional test matrix

| Case | Expected result |
|---|---|
| New signup with valid data | Client account created; never admin; success message displayed |
| Password confirmation mismatch | No account inserted; validation error displayed |
| Invalid email / username / short password | Rejected server-side |
| Duplicate username | Rejected; no duplicate account |
| Duplicate normalized email | Rejected when `uq_clients_email` migration is in place |
| Signup/login without CSRF | Rejected before database mutation/authentication |
| Wrong login password | Generic error; failed attempt tracked |
| Eight failed attempts for identifier/IP bucket | Temporary 15-minute throttle response |
| Login with username | Authentication succeeds and role routing is correct |
| Login with email | Authentication succeeds and role routing is correct |
| Existing admin login | Admin dashboard; `rank` and legacy `user_rank` agree |
| Client with no vendor | Client dashboard |
| Client with one vendor | Vendor dashboard with owned vendor selected |
| Client with multiple vendors | Vendor selection page |
| Tampered vendor ID | 403; session vendor context is not changed |
| Logout GET | 405; session remains intact |
| Logout POST with invalid CSRF | 403; session remains intact |
| Logout POST with valid CSRF | Session and cookie cleared |
| Valid password-reset token | Password updated and token marked used |
| Expired/reused reset token | Rejected; password unchanged |
| Password reset for unknown email | Same public response as known email |
| Password-reset email | Delivered only when mail transport is configured; URL uses `CNRBYTE_BASE_URL` |
| Direct anonymous dashboard access | Redirected to login |

## Limits
No live MySQL/MariaDB server, browser session, mail transport, or staging database was available in this run. Static checks do not replace the staging functional matrix.
