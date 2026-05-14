# Documentation Audit

Created on 2026-05-14 after switching ITPortal to PHP native.

## Ready

- PHP native stack locked.
- MVP scope locked.
- Data model drafted.
- Routes/controllers drafted.
- Export spec drafted.
- Source materials/import plan drafted.
- Security checklist drafted.
- Claude prompt ready.

## Still Needed During Implementation

- Exact PHP version on VPS.
- Exact MySQL/MariaDB version.
- Domain/subdomain.
- Initial admin credential process.
- Final mapping for dealer/DpackWeb/certificate source files.
- Final Excel/PDF visual tuning.

## Risk

| Risk | Mitigation |
|------|------------|
| PHP native becomes messy | Use controller/service/repository/view separation |
| PDF table too dense | Excel is source of truth, PDF landscape |
| Mobile table overflow | Use ticket cards |
| Scope creep to monitoring | Keep monitoring out of V1 |
