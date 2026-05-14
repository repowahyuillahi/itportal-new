# Design Decisions

## ADR-001: PHP Native Modular

Accepted.

Reason: owner prefers a lightweight stack that is easy to understand, easy to
deploy on aaPanel, and does not require Laravel, Next.js, React app, or Go.

## ADR-002: Server-Rendered Views

Accepted.

Reason: ITPortal is an internal CRUD/reporting tool. Server-rendered PHP views
are simpler, fast enough, and easier to maintain.

## ADR-003: MySQL/MariaDB

Accepted.

Reason: MySQL/MariaDB fits aaPanel/VPS deployment and is sufficient for ticket,
master data, and report workloads.

## ADR-004: Excel/PDF First

Accepted.

Reason: meeting output needs Excel and PDF first. PPT export is postponed.

## ADR-005: Source Materials Are Import References

Accepted.

Reason: Excel/CSV/PPTX files in the project root are source materials. Claude
must inspect and map them before importing. Do not hardcode their content into
application code.

