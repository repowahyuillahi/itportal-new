# Security Checklist

## Foundation

- [ ] `.env` is not committed.
- [ ] Production debug display is off.
- [ ] Errors are logged privately.
- [ ] Public web root is `public/`.
- [ ] `storage/`, `config/`, `app/`, `database/` are not web-accessible.

## Auth

- [x] Passwords use `password_hash()`.
- [x] Login uses `password_verify()`.
- [x] Session ID regenerates after login.
- [x] Logout destroys session.
- [x] Session cookie is HttpOnly.
- [x] Secure cookie is enabled in production (`APP_ENV=production`).

## Forms

- [x] Every POST form has CSRF token (`csrf_field()` helper).
- [x] CSRF token is verified server-side (`CsrfMiddleware`).
- [x] Validation errors preserve safe old input (`old()` helper, password never echoed back).
- [x] Output is escaped in views (`e()` helper).

## Database

- [x] All queries use PDO prepared statements.
- [x] No user input is concatenated into SQL.
- [x] Migrations are used for schema.
- [ ] Database backup is documented. *(Phase 11)*

## Roles

- [x] Manager/viewer cannot mutate data. *(verified: tickets Phase 4, master data Phase 5; smoke tests cover 403 paths)*
- [x] Master data mutation requires admin. *(Phase 5: dealers + items)*
- [x] Ticket mutation requires admin or it_staff. *(Phase 4)*
- [ ] Export requires authenticated user. *(Phase 7)*

## Export/Upload

- [ ] Export file path is not user-controlled.
- [ ] Downloads require login.
- [ ] Upload MIME/size is validated.
- [ ] Uploaded files cannot execute.

## Pre-Launch

- [ ] Login/logout tested.
- [ ] Role checks tested.
- [ ] CSRF tested.
- [ ] Lead time tested.
- [ ] Excel/PDF tested.
- [ ] Mobile form tested.

