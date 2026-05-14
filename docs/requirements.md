# Requirements

## Tickets

- User yang login sebagai `admin` atau `it_staff` bisa membuat ticket/laporan IT.
- Ticket menyimpan status, tanggal, pelapor, dealer, laporan awal, pengecekan,
  solusi, item/kategori, waktu mulai, waktu selesai, teknisi, dan lampiran
  opsional.
- Lead time dihitung di backend dari waktu mulai sampai waktu selesai.
- Ticket yang belum selesai boleh menampilkan lead time berjalan di UI, tetapi
  tidak dianggap closed.
- Ticket bisa difilter berdasarkan bulan, tahun, status, dealer, item, dan kata
  kunci.

## Master Data

- Admin bisa mengelola dealer.
- Admin bisa mengelola item/kategori.
- Dealer/item inactive tetap dipertahankan untuk data historis, tetapi tidak
  muncul sebagai pilihan default saat membuat ticket baru.

## Dashboard

- Dashboard menampilkan ringkasan bulan berjalan atau bulan yang dipilih.
- Ringkasan minimal: total ticket, open, in progress, pending, closed, average
  lead time, top dealer, top item.

## Export

- Export Excel dan PDF mengikuti `docs/export-report-spec.md`.
- Export memakai filter report yang sama dengan halaman report.
- Excel adalah output utama untuk tabel detail.
- PDF dibuat landscape dan meeting-readable.

## Security

- Semua halaman aplikasi butuh login kecuali `/login` dan `/health`.
- Manager dan viewer read-only.
- Semua POST memakai CSRF token.
- Semua query memakai PDO prepared statements.

## Mobile

- Ticket list di mobile tampil sebagai cards.
- Form input nyaman di HP.
- Tabel lebar hanya untuk desktop/export.

