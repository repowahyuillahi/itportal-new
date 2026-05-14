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

