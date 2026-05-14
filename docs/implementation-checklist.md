# Implementation Checklist

## Phase 0 - Source Materials And Scope

- [x] Read all docs.
- [x] Inspect source materials listed in `docs/source-materials.md`.
- [x] Confirm V1 exclusions: no dealer login, no monitoring automation, no PPT export.
- [x] Identify dealer, item, DpackWeb, certificate, CCTV, monitoring, and report data candidates (see `docs/source-inspection.md`).

## Phase 1 - PHP Foundation

- [x] Create `composer.json`.
- [x] Create `public/index.php`.
- [x] Create `app/Core` router, request, response, session, view, database helpers.
- [x] Create `config` loader and `.env.example`.
- [x] Create base layout in `resources/views/layouts`.
- [x] Create `public/assets/css/app.css`.
- [x] Create health route.
- [x] Add simple dev command docs (`docs/local-dev.md`).

## Phase 2 - Database

- [x] Create migrations table.
- [x] Create users table.
- [x] Create sessions table.
- [x] Create dealers table.
- [x] Create items table.
- [x] Create tickets table.
- [x] Create ticket_attachments table.
- [x] Create export_jobs table.
- [x] Create audit_logs table.
- [x] Seed default items.
- [x] Seed initial admin user method (`scripts/create_admin.php` + env-driven seeder).

## Phase 3 - Auth And Roles

- [x] Implement login page/action.
- [x] Implement logout.
- [x] Implement session auth.
- [x] Implement role guard (`RoleMiddleware::only([...])`).
- [x] Implement CSRF helper (`CsrfMiddleware`, applied to all POST routes).
- [x] Implement audit helper (`AuditService` + `audit_logs` insert for login/logout).

## Phase 4 - Ticket Core

- [x] Ticket list with filters (`month`, `year`, `status`, `dealer_id`, `item_id`, `q`, `page`).
- [x] Ticket create form/action.
- [x] Ticket detail.
- [x] Ticket edit form/action.
- [x] Ticket close action (with lead time & closed_by/closed_at).
- [x] Lead time calculation (`lead_time_seconds = finished_at - started_at`).
- [ ] Attachment metadata. *(deferred - upload UI not in Phase 4 scope; row-level support exists via migrations)*

## Phase 5 - Master Data

- [x] Dealer list/create/edit/activate/deactivate (admin-only mutating, audit `dealer.create/update/status`).
- [x] Item list/create/edit/activate/deactivate (admin-only mutating, audit `item.create/update/status`).
- [x] Hide inactive records from new-ticket select fields (`listActive()` only).
- [ ] Add import planning page/script after source files are mapped. *(deferred - import not in V1 scope)*

## Phase 6 - Dashboard And UI

- [x] Dashboard summary cards (total, status breakdown, avg lead time, top dealers, top items, recent tickets).
- [x] Desktop ticket table + mobile cards (already in Phase 4, polished here).
- [x] Responsive forms (sticky form-actions on mobile for ticket form).
- [x] Report filter page `/reports/monthly` (filters: month/year/status/dealer/item/q; export buttons disabled placeholders).
- [x] Error pages 403/404/419 rendered through `layouts/app` via `Response::errorPage()`.
- [x] Skip-to-content link + `<main id="content" tabindex="-1">` for keyboard a11y.

## Phase 7 - Export

- [ ] Monthly report query.
- [ ] Excel export using PhpSpreadsheet.
- [ ] PDF export using Dompdf or mPDF.
- [ ] Export file records.
- [ ] Download action.

## Phase 8 - Source Data Import

- [ ] Create dry-run importer for dealer data once source file is identified.
- [ ] Create dry-run importer for item/category candidates.
- [ ] Create dry-run importer for certificate/DpackWeb data if included in V1.
- [ ] Import only after owner reviews dry-run output.

## Phase 9 - Security And QA

- [ ] Complete security checklist.
- [ ] Test auth/roles.
- [ ] Test CSRF.
- [ ] Test lead time.
- [ ] Test filters.
- [ ] Test Excel/PDF output.
- [ ] Test mobile layout.

## Phase 10 - Deployment

- [ ] Add aaPanel/Nginx deployment notes.
- [ ] Add backup/restore notes.
- [ ] Add smoke test.

