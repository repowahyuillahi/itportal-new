# Source Materials Inspection (Phase 0)

Hasil scan otomatis lewat `scripts/inspect_sources.php`. Belum ada data yang
diimport ke MySQL. Tujuan dokumen ini: catat file apa saja, kemungkinan isinya,
dan calon mapping ke schema di `docs/data-model.md`.

Output mentah tersimpan di `scripts/source-inspection.log`.

## File Yang Ditemukan Di Root

| File | Size | Tipe |
|------|------|------|
| `0426-WahyuIllahi.pptx` | ~21 MB | PPTX, bahan meeting (referensi saja, tidak diimport) |
| `Weekly Report.xlsx` | ~15 KB | Excel, CCTV monitoring report mingguan |
| `2026-IT-Monitoring.xlsx` | ~52 KB | Excel multi-sheet (CCTV, Server, Network, Jadwal, Daily, Weekly, Monthly) |
| `10-Fauzan-DailyReport.xlsx` | ~25 KB | Excel, daily report PIC + history printer |
| `25 Maret 2026.xlsx` | ~15 KB | Excel, CCTV monitoring report harian |
| `CCTV Access Control Maret 2026.xlsx` | ~16 KB | Excel, akses device CCTV dan user sharing |
| `Improvement_Report_Monitoring_Radio_Server_CCTV.xlsx` | ~31 KB | Excel multi-sheet, monitoring CCTV/Server/Radio per tanggal |
| `data-lengkap-sertifikat.csv` | ~25 KB | CSV, sertifikat DpackWeb per dealer |

## Ringkasan Per File

### `data-lengkap-sertifikat.csv`

Kolom: `area, folder_area, branch, file_p12, file_pin, cert_code, dealer_code,
pin, subject, issuer, valid_from, valid_to, thumbprint_sha1, serial_number`.

Catatan:

- Kolom pertama punya BOM UTF-8 (`\xEF\xBB\xBF`) - perlu ditangani importer.
- `valid_from`/`valid_to` format `M/D/YYYY HH:MM AM/PM`.
- `pin` bersifat sensitif - jangan tampil polos di UI.

Calon mapping (post-V1, tidak masuk V1):

- `branch` -> kandidat nama dealer.
- `dealer_code`, `cert_code` -> kandidat identitas dealer/sertifikat.
- Sisanya butuh entitas baru `certificates` (belum ada di `data-model.md`).

### `Weekly Report.xlsx`

- Sheet `CCTV`. Title bar `CCTV MONITORING REPORT - 09 MEI 2026`.
- Header: `NO, CATEGORY, LOKASI, QTY, HOSTNAME, IP Address DVR/NVR, OFF, ON %,
  TIME %, LAST SAVED RECORD, DETAILS, KETERANGAN, FIX UP DATE`.
- Bukan ticket. Ini laporan device monitoring -> di luar V1 (V1 tidak ada
  monitoring otomatis).

### `2026-IT-Monitoring.xlsx`

Multi-sheet:

- `Perangkat` - daftar device: CCTV (LOKASI, JENIS, IP), Server (HOSTNAME,
  LOKASI, IP), Network (NAMA PERANGKAT, HOSTNAME, IP).
- `Jadwal`, `Daily`, `Maret`, `Weekly`, `Monthly` - checklist maintenance
  (`OK`/blank) per tanggal terhadap server `TBDATA, TBSPC, TBPDC, TBSERVER,
  ODOO, TBAPP`.
- `referensi` - link artikel.

Calon mapping:

- Sheet `Perangkat` boleh jadi inspirasi master `items`/`devices`, tapi
  schema saat ini hanya punya `items` (kategori), bukan `devices` real.
- Sheet checklist = monitoring rutin -> di luar V1.

### `10-Fauzan-DailyReport.xlsx`

- Sheet `10`: header `PIC, CODE, OBJECTIVE, M.QTY, M.TIME, SPT, UNIT,
  1.QTY..6.DETAILS`. Format daily activity log per PIC.
- Sheet `Printer`: history isi ulang tinta printer.

Calon mapping:

- Kolom `OBJECTIVE` + `n.DETAILS` mirip narasi ticket, tapi schema-nya
  tidak 1:1 ke `tickets`. Tunda sampai owner konfirmasi.
- Sheet printer = device specific, di luar V1.

### `25 Maret 2026.xlsx`

- Sheet `CCTV` - sama persis bentuknya dengan `Weekly Report.xlsx`. Snapshot
  harian. Di luar V1.

### `CCTV Access Control Maret 2026.xlsx`

- `Device Management`: `Alias, Device Domain, Device Serial No., IP/Port,
  Status`.
- `My Shared Devices`: `Email/Contact, Role/Deskripsi, Nama, Devices Shared`.
- `Device Access`: `Device Alias, Status, User Access`.

Calon mapping:

- Daftar device CCTV per cabang -> kandidat sub-master, tapi V1 belum punya
  entitas `cctv_devices`. Di luar V1.

### `Improvement_Report_Monitoring_Radio_Server_CCTV.xlsx`

- `CCTV`, `Server`, `Radio`, `Radio (2)` - laporan monitoring uptime per
  tanggal (1..31). Di luar V1.

### `0426-WahyuIllahi.pptx`

- PPT meeting. Referensi visual untuk format `Support Maintenance Report`.
- Tidak diparse dan tidak diimport.

## Kandidat Yang Berpotensi Masuk V1

Schema V1 (`tickets`, `dealers`, `items`) tidak menemukan sumber data ticket
historis yang clean dari file di atas. Yang paling relevan:

- `data-lengkap-sertifikat.csv` -> hanya kolom `branch` / `dealer_code` yang
  bisa jadi seed awal `dealers`, dengan catatan: ini data DpackWeb, bukan
  daftar dealer resmi dari owner. **Jangan auto-import.** Owner perlu kasih
  daftar dealer canonical.
- Items master sudah dideklarasikan eksplisit di `docs/data-model.md` -
  seeder pakai daftar itu, bukan dari Excel.

## Konfirmasi Scope V1

Sesuai `README.md`, `CLAUDE.md`, dan `docs/implementation-plan.md`,
**V1 TIDAK mencakup**:

- Dealer login sendiri.
- Monitoring server/jaringan otomatis (CCTV/Server/Radio uptime, dsb).
- Export PPT.
- Notifikasi WhatsApp/email.
- SLA kompleks.

Mayoritas Excel di atas adalah data monitoring -> di luar V1. Akan ditangani
pasca-V1 lewat importer terpisah dengan dry-run dulu.

## Next Step Phase 0

- Tidak ada import. Phase 0 selesai begitu dokumen ini dibaca owner.
- Sebelum Phase 8 (Source Data Import) dimulai, owner perlu menyediakan
  daftar dealer canonical (Excel/CSV) yang bisa di-dry-run map ke tabel
  `dealers`.
