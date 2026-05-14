# Frontend

## Direction

ITPortal UI harus terasa seperti dashboard kerja internal: padat, jelas, cepat,
dan nyaman di HP.

No React app. No SPA required.

## UI Stack

- Server-rendered PHP views.
- Responsive CSS in `public/assets/css/app.css`.
- Minimal vanilla JS in `public/assets/js/app.js`.
- Optional: small JS only for date/time helpers, filter drawer, confirm dialog.

## Desktop

Layout:

- sidebar left;
- topbar with active month/filter;
- content area.

Menu:

- Dashboard
- Tickets
- Reports
- Dealers
- Items
- Users
- Settings

## Mobile

Use:

- top compact header;
- bottom nav or simple menu button;
- ticket cards;
- filter drawer;
- touch-friendly form fields.

Do not force the full report table on mobile.

## Ticket Form

Sections:

1. Pelapor dan Dealer
2. Laporan Awal dan Item
3. Status dan Waktu
4. Pengecekan dan Solusi
5. Lampiran dan Submit

Required fields:

- status;
- tanggal;
- pelapor;
- dealer;
- laporan awal;
- item;
- waktu mulai.

## Dashboard

Show:

- total ticket bulan ini;
- open;
- in progress;
- pending;
- closed;
- average lead time;
- top dealer;
- top item.

Keep charts simple. CSS bars are acceptable for V1.

## Phase 6 UI Conventions

Implementation lives in `resources/views/dashboard/index.php`,
`resources/views/reports/monthly.php`, and shared classes in
`public/assets/css/app.css`.

### Page Header

```html
<section class="page-header">
    <h1>Title</h1>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="...">Primary action</a>
    </div>
</section>
```

`.page-header` is `flex; justify-content:space-between; flex-wrap:wrap`,
so on mobile the actions wrap below the title without overflowing.

### Filter Bar

```html
<form method="get" action="..." class="card filter-bar" aria-label="...">
    <div class="filter-grid">
        <div class="field">...</div>
    </div>
    <div class="filter-actions">
        <button class="btn btn-primary">Terapkan</button>
        <a class="btn" href="...">Reset</a>
    </div>
</form>
```

`.filter-grid` uses `auto-fit minmax(150px, 1fr)`. `.field-grow`
spans 2 columns on desktop, 1 on mobile.

### Stat Cards

`.stat-grid` (auto-fit `minmax(180px, 1fr)`) holds `<article class="stat-card">`
nodes with three children:

- `.stat-label` (small, uppercase muted label or status badge)
- `.stat-value` (large numeric)
- a deep-link `<a>` to `/tickets?...` filtered by that status

### Top Lists

`<ol class="rank-list">` with each `<li>` containing the entity link plus
a right-aligned `.rank-count`.

### Tables vs Mobile Cards

Always render both `.table-wrap > .data-table` (desktop) and
`.cards-mobile` (mobile). CSS toggles them at the `720px` breakpoint:

```css
@media (max-width: 720px) {
    .table-wrap { display: none; }
    .cards-mobile { display: block; }
}
```

Never force the full report table on mobile.

### Status Badges

Status name is mapped to a CSS class via underscore→hyphen:
`open` → `.badge-open`, `in_progress` → `.badge-in-progress`, etc.

### Accessibility

- `<a class="skip-link" href="#content">Lewati ke konten</a>` is the first
  focusable element in the body. CSS hides it offscreen unless `:focus`.
- `<main id="content" tabindex="-1">` so the skip link can move focus.
- `<nav aria-label="Navigasi utama">` and filter forms use `aria-label`.
- Disabled placeholder buttons (e.g. Phase 7 export) are real `<button
  disabled aria-disabled="true">` so screen readers announce them
  correctly.

### Sticky Form Actions (mobile)

Long ticket forms keep `Simpan` reachable via a sticky bottom bar that
is only enabled below `640px` (`.ticket-form .form-actions { position:
sticky; bottom: 0; }`).

### Error Pages

`resources/views/errors/generic.php` renders inside `layouts/app` via
`Response::errorPage($status, $heading, $message)`. The page provides
context-aware navigation:

- "Kembali ke Beranda" -> `/`
- "Dashboard" if logged in, else "Login"

