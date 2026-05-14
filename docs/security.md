# Security

## Implementation Status

Phase 3 wired:

- Passwords stored with `password_hash(PASSWORD_DEFAULT)`; verified with
  `password_verify()`.
- Session ID regenerated on login (`Auth::login` -> `Session::regenerate`).
- CSRF token rotated on login.
- Logout destroys the auth identity in `$_SESSION` and regenerates the ID.
- `CsrfMiddleware` rejects any POST without a valid `_csrf` token (419).
- `AuthMiddleware` blocks unauthenticated access to protected routes.
- `RoleMiddleware::only([...])` enforces role-based access (returns 403).
- `audit_logs` records `login.success`, `login.failed` (with reason),
  and `logout`. IP and User-Agent are stored as SHA-256 hashes only.
- Inactive users (`status != 'active'`) are rejected at login.
- A dummy `password_verify()` is run on unknown emails to keep timing
  roughly constant.


Use `security-checklist.md` as the implementation gate.

## Auth

- All app pages require login except `/login` and `/health`.
- Passwords use `password_hash()`.
- Login regenerates session ID.
- Logout destroys session.
- Session timeout is required.

## Roles

- `admin`: full access.
- `it_staff`: create/update/close tickets and export reports.
- `manager`: dashboard/report read-only.
- `viewer`: read-only.

## CSRF

All POST routes require CSRF token.

## SQL

All database access uses PDO prepared statements.

## Uploads

If attachments are enabled:

- validate MIME;
- validate size;
- randomize stored filename;
- store outside public source;
- require login for download.

## Errors

Production must not expose:

- stack traces;
- SQL errors;
- server paths;
- `.env`;
- secrets.

## Audit Logs

Audit:

- ticket create/update/close;
- dealer/item changes;
- export creation;
- role/status changes.

