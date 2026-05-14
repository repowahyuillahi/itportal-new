---
name: itportal
description: Use when building, reviewing, planning, or documenting ITPortal, an internal IT Helpdesk and Support Maintenance Report system using PHP native modular architecture, MySQL/MariaDB, mobile-friendly forms, dashboard, and Excel/PDF export.
---

# ITPortal Skill

Use this skill for all ITPortal work.

## Mission

Build ITPortal as a lightweight internal IT operations portal:

- ticket/input laporan IT;
- master dealer;
- master item/kategori;
- dashboard bulanan;
- report meeting;
- Excel/PDF export;
- mobile-friendly daily input.

## Stack

- PHP native modular.
- MySQL/MariaDB.
- Server-rendered PHP views.
- Responsive CSS.
- Minimal vanilla JavaScript.
- Composer only for autoload and small libraries.
- Excel/PDF export.

Do not use Laravel, Next.js, React app, or Go in V1.

## Read Order

1. `CLAUDE.md`
2. `README.md`
3. `docs/implementation-plan.md`
4. `docs/php-native-patterns.md`
5. `docs/export-report-spec.md`
6. `docs/implementation-checklist.md`
7. `docs/source-materials.md`

Task-specific:

- UI: `docs/frontend.md`
- Database: `docs/data-model.md`
- Routes/controllers: `docs/routes-and-controllers.md`
- Source/import files: `docs/source-materials.md`
- Security: `docs/security.md`, `docs/security-checklist.md`
- Deployment: `docs/deployment.md`, `docs/configuration.md`

## Rules

- Keep code readable for a PHP-native owner.
- Use a front controller in `public/index.php`.
- Use clear controllers, services, repositories, and views.
- Use PDO prepared statements.
- Use CSRF for forms.
- Backend calculates lead time.
- Mobile ticket UI uses cards, not forced wide tables.
- Excel/PDF column order follows `docs/export-report-spec.md`.
- Update docs when schema/routes/config change.
