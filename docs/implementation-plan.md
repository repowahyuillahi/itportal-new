# Implementation Plan

## Phase 0 - Scope And Reference

- Read all docs.
- Inspect `0426-WahyuIllahi.pptx` only as meeting context.
- Keep V1 focused: tickets, dealers, items, dashboard, Excel/PDF.

## Phase 1 - PHP Foundation

- Create Composer project.
- Create folder layout from `docs/architecture.md`.
- Add front controller `public/index.php`.
- Add Router, Request, Response, View, Session, Database, Csrf, Auth.
- Add `.env.example`.
- Add health route.
- Add base layout and login placeholder.

## Phase 2 - Database

- Add migration runner.
- Add migrations from `docs/data-model.md`.
- Seed default item list.
- Seed initial admin user method.

## Phase 3 - Auth

- Login/logout.
- Session middleware.
- Role guard.
- CSRF middleware.
- Flash messages.

## Phase 4 - Ticket

- Ticket list/filter.
- Ticket create.
- Ticket detail.
- Ticket edit.
- Ticket close.
- Lead time calculation.

## Phase 5 - Master Data

- Dealer CRUD.
- Item CRUD.
- Active/inactive behavior.

## Phase 6 - Dashboard And Reports

- Dashboard monthly summary.
- Report filter page.
- Desktop table.
- Mobile cards.

## Phase 7 - Export

- Excel export.
- PDF export.
- Export file records.
- Download response.

## Phase 8 - QA And Deployment

- Security checklist.
- Manual tests.
- aaPanel deployment notes.
- Backup notes.

