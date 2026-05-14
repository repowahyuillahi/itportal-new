# Security Checklist

## Foundation

- [x] `.env` is not committed (`.gitignore` covers it).
- [x] Production debug display is off (`APP_DEBUG=false` in `.env.example`; bootstrap honors `app.debug`).
- [x] Errors are logged privately (`storage/logs/php-error.log`, gitignored).
- [x] Public web root is `public/` (front controller `public/index.php`).
- [x] `storage/`, `config/`, `app/`, `database/` are not web-accessible (Apache `Require all denied` `.htaccess` in each + root rewrite to `public/`).

## Auth

- [x] Passwords use `password_hash()`.
- [x] Login uses `password_verify()`.
- [x] Session ID regenerates after login (`Auth::login()` -> `Session::regenerate()`).
- [x] Logout destroys session + rotates session id.
- [x] Session cookie is HttpOnly.
- [x] Secure cookie is enabled in production (`APP_ENV=production`).
- [x] Login throttling - `LoginThrottle` blocks after 5 fails per (IP+email) or 20 fails per IP within 15 min. Returns `429` + `Retry-After`.
- [x] Timing-equalized password check on user-not-found (dummy `password_verify`).
- [x] Failed login attempts audited (`audit_logs.action='login.failed'`).

## Forms

- [x] Every POST form has CSRF token (`csrf_field()` helper).
- [x] CSRF token is verified server-side (`CsrfMiddleware`).
- [x] CSRF token rotates on login (`Csrf::rotate()`).
- [x] Validation errors preserve safe old input (`old()` helper, password never echoed back).
- [x] Output is escaped in views (`e()` helper, smoke test asserts no raw `<script>` echo).

## Headers (Phase 9)

- [x] `X-Content-Type-Options: nosniff` on every response.
- [x] `X-Frame-Options: DENY` on every response.
- [x] `Referrer-Policy: same-origin` on every response.
- [x] `Content-Security-Policy` with `default-src 'self'`, `frame-ancestors 'none'`, `object-src 'none'`, `form-action 'self'`. Inline `<script>` is forbidden; `'unsafe-inline'` is allowed only in `style-src` (TODO: hash-pin inline CSS in a future iteration).
- [x] `Strict-Transport-Security: max-age=31536000; includeSubDomains` only when `APP_ENV=production`.
- [x] HTTP -> HTTPS redirect in production (honors `X-Forwarded-Proto` for proxies).

## Database

- [x] All queries use PDO prepared statements.
- [x] No user input is concatenated into SQL.
- [x] Migrations are used for schema.
- [ ] Database backup is documented. *(deferred to Phase 11)*

## Roles

- [x] Manager/viewer cannot mutate data. *(verified: tickets Phase 4, master data Phase 5; smoke tests cover 403 paths)*
- [x] Master data mutation requires admin. *(Phase 5: dealers + items)*
- [x] Ticket mutation requires admin or it_staff. *(Phase 4)*
- [x] Export requires authenticated user. *(Phase 7)*

## Export/Upload

- [x] Export file path is not user-controlled (`ExportService` builds `Support_Maintenance_Report_{Y}-{M}_{stamp}.{ext}`; smoke test asserts no `..` or path separators in `Content-Disposition`).
- [x] Downloads require login (`AuthMiddleware` on `/exports/monthly/*`).
- [x] Filename header sanitized in `Response::download()` (strips `\\`, `/`, CR, LF, `"`).
- [x] Export file type whitelisted (`.xlsx`, `.pdf` only).
- [ ] Upload MIME/size is validated. *(no upload feature in V1)*
- [ ] Uploaded files cannot execute. *(no upload feature in V1)*

## Pre-Launch (Phase 6/7/9 verified)

- [x] Login/logout tested. *(`scripts/smoke_phase4.php`, `scripts/smoke_phase9.php`)*
- [x] Role checks tested (admin / it_staff / manager / viewer 403 cases).
- [x] CSRF tested - missing + wrong token both -> 419.
- [x] Lead time tested *(Phase 4)*.
- [x] Filters tested *(Phase 6 + 7)*.
- [x] Excel/PDF output tested *(Phase 7 smoke; visual QA approved)*.
- [x] Mobile form tested *(Phase 6 sticky form-actions, table/cards toggle)*.
- [x] Penetration smoke (XSS escape, CSRF, IDOR, headers, throttle, path traversal, session fixation): `scripts/smoke_phase9.php`.

## Production Deploy Checklist

Before flipping `APP_ENV=production`:

1. **Composer**: restore default Packagist (remove the `repositories[0]` Aliyun mirror in `composer.json` if your prod environment can reach `repo.packagist.org`). Re-enable `audit.block-insecure: true` (or run `composer audit` in CI).
2. **PHP extensions**: confirm `gd` + `zip` enabled on the prod PHP build (required by PhpSpreadsheet).
3. **`.env`**: set `APP_ENV=production`, `APP_DEBUG=false`, real `DB_*`, real `SESSION_NAME`. Never commit.
4. **HTTPS**: deploy behind TLS terminator. App's `Security::enforceHttpsIfProduction()` will 301-redirect any plain-HTTP request.
5. **Web server doc root**: must point at `public/`. The root `.htaccess` is a *safety net*, not a substitute.
6. **Storage permissions**: `storage/` writable by web user, mode 750 max. Files inside must NOT be web-accessible (per `storage/.htaccess`).
7. **Run migrations**: `php scripts/migrate.php`. Confirm `php scripts/migrate.php --status` shows all applied.
8. **Smoke tests**: run `smoke_phase4.php` through `smoke_phase9.php` against the staging URL.
9. **Backups**: schedule a `mysqldump` cron *(Phase 11; document at minimum)*.
10. **Monitoring**: tail `storage/logs/php-error.log` for the first 24 hours.

