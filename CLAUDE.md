# Claude Agent Guide - ITPortal

Kamu adalah Claude Opus 4.7 sebagai implementer utama ITPortal.

Project ini memakai PHP native modular. Jangan mengubah stack tanpa approval
owner.

## Baca Dulu

Baca file berikut sebelum coding:

1. `README.md`
2. `.claude/skills/itportal/SKILL.md`
3. `docs/product-vision.md`
4. `docs/requirements.md`
5. `docs/design-decisions.md`
6. `docs/architecture.md`
7. `docs/php-native-patterns.md`
8. `docs/implementation-plan.md`
9. `docs/implementation-checklist.md`
10. `docs/source-materials.md`
11. `docs/data-model.md`
12. `docs/api-reference.md`
13. `docs/routes-and-controllers.md`
14. `docs/export-report-spec.md`

Untuk security-sensitive work, baca juga:

- `docs/security.md`
- `docs/security-checklist.md`

## Stack Lock

- PHP native modular
- MySQL/MariaDB
- Server-rendered PHP views
- Responsive CSS
- Minimal vanilla JavaScript
- Composer hanya untuk autoload dan library kecil
- Excel/PDF export

## Scope V1

Bangun:

- login/logout;
- role internal: admin, it_staff, manager, viewer;
- dashboard;
- ticket/laporan IT;
- dealer;
- item/kategori;
- report bulanan;
- export Excel/PDF;
- mobile-friendly form dan ticket cards.

Jangan bangun di V1:

- dealer login;
- monitoring otomatis;
- PPT export;
- WhatsApp/email notification;
- SLA kompleks.

## Working Rules

- Ikuti `docs/implementation-plan.md` dan `docs/implementation-checklist.md`.
- Buat kode yang mudah dibaca pemilik project.
- Jangan buat framework mini yang terlalu rumit.
- Tetap pisahkan controller, service, repository, view.
- Semua query database pakai PDO prepared statements.
- Semua POST form pakai CSRF token.
- Business rules seperti lead time, status, export, dan role check berada di backend.
- Update docs jika schema, route, config, atau arsitektur berubah.
- `0426-WahyuIllahi.pptx` hanya bahan referensi, jangan hardcode isi slidenya.
- File Excel/CSV/PPTX lain di root project adalah source materials. Inspect dan
  dry-run mapping dulu sebelum import.

## Done Response

Setiap selesai task, laporkan:

- phase yang dikerjakan;
- file dibuat/diubah;
- behavior yang ditambah;
- cara menjalankan;
- test/check;
- gap;
- next step.
