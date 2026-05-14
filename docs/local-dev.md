# Local Development

Catatan jalankan ITPortal di Windows + XAMPP. Linux/Mac sama saja, hanya
ganti path `php`.

## Prasyarat

- PHP 8.2+ (XAMPP `C:\xampp\php\php.exe` sudah cukup).
- Extension yang dipakai: `pdo_mysql`, `mbstring`, `json`, `zip`, `xml`,
  `openssl`.
- MySQL/MariaDB lokal (XAMPP MySQL OK).
- Composer opsional di Phase 1 (foundation jalan tanpa Composer karena
  ada built-in PSR-4 autoloader). Pasang Composer sebelum Phase 7 untuk
  PhpSpreadsheet/Dompdf.

## Setup Pertama Kali

```powershell
# 1. Copy env contoh
Copy-Item .env.example .env

# 2. Edit .env, isi DB_DATABASE / DB_USERNAME / DB_PASSWORD sesuai MySQL lokal.
notepad .env
```

## Cek PHP Punya Extension Yang Dibutuhkan

```powershell
& 'C:\xampp\php\php.exe' -m | Select-String -Pattern "pdo|zip|mbstring|json|openssl"
```

Kalau `zip` belum aktif di CLI, tambahkan `extension=zip` di
`C:\xampp\php\php.ini` (section `[PHP]`) atau jalankan dengan flag
`-d extension=zip`.

## Jalankan Built-in Web Server

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public
```

Buka:

- <http://127.0.0.1:8000/> -> redirect ke `/login`.
- <http://127.0.0.1:8000/login> -> form login placeholder.
- <http://127.0.0.1:8000/health> -> JSON status.

## Phase 0 Source Inspection (Opsional)

```powershell
& 'C:\xampp\php\php.exe' -d extension=zip scripts/inspect_sources.php
```

Output ringkasan ditulis ke stdout. Log tersimpan di
`scripts/source-inspection.log` (di-`.gitignore`).

## Database (Phase 2)

```powershell
# 1. Pastikan MySQL/MariaDB jalan, lalu buat database kosong:
#    Lewat phpMyAdmin atau:
#    & 'C:\xampp\mysql\bin\mysql.exe' -uroot -e "CREATE DATABASE itportal CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 2. Jalankan migrations
& 'C:\xampp\php\php.exe' scripts/migrate.php

# Cek status apa saja yang sudah jalan
& 'C:\xampp\php\php.exe' scripts/migrate.php --status

# Reset total (hanya local; akan minta konfirmasi "yes")
& 'C:\xampp\php\php.exe' scripts/migrate.php --fresh

# 3. Seed default data (items, optional admin via .env)
& 'C:\xampp\php\php.exe' scripts/seed.php

# Atau jalankan satu seeder spesifik:
& 'C:\xampp\php\php.exe' scripts/seed.php items

# 4. Buat admin lewat CLI interaktif
& 'C:\xampp\php\php.exe' scripts/create_admin.php
# Atau non-interaktif:
& 'C:\xampp\php\php.exe' scripts/create_admin.php --email=admin@itportal.local --name=Admin --password=secret123
```

## Smoke Test Phase 9 (Security & QA)

Penetration smoke: security headers, login throttle (429 setelah 5
percobaan gagal/email atau 20/IP per 15 menit), CSRF enforcement, IDOR,
XSS escape, path traversal di filename export, dan session fixation
guard. Semua jalan tanpa menulis ke DB selain audit_logs (yang memang
intended untuk login.failed).

```powershell
$proc = Start-Process -FilePath 'C:\xampp\php\php.exe' `
    -ArgumentList "-S","127.0.0.1:8772","-t","public" `
    -WindowStyle Hidden -PassThru
Start-Sleep -Milliseconds 1500
& 'C:\xampp\php\php.exe' scripts/smoke_phase9.php
Stop-Process -Id $proc.Id -Force
```

Smoke test akan **membersihkan sendiri** baris `audit_logs` yang
dibuatnya untuk email throttle test (`throttle@itportal.local`,
`someone-else@itportal.local`).

Untuk uji throttle manual:

```powershell
# 1) Coba login salah 6 kali dengan email yang sama -> 429.
# 2) Tunggu 15 menit ATAU bersihkan via SQL:
#    DELETE FROM audit_logs WHERE action='login.failed' AND created_at > NOW() - INTERVAL 1 HOUR;
```

## Smoke Test Phase 8 (Source Data Import Dry-Run)

Memvalidasi bahwa kedua CLI dry-run berjalan tanpa menulis ke DB,
mengeluarkan diff report yang valid, masking PIN, menyembunyikan p12
absolute paths, dan tidak mengubah jumlah baris di `dealers`, `tickets`,
`items`, `users`, `audit_logs`, `export_jobs`.

```powershell
# Dry-run dealer + certificate (read-only, no DB writes):
& 'C:\xampp\php\php.exe' scripts/import_dryrun_dealers.php
& 'C:\xampp\php\php.exe' scripts/import_dryrun_certificates.php

# JSON output untuk review owner:
& 'C:\xampp\php\php.exe' scripts/import_dryrun_dealers.php --json `
    > storage\exports\dealer-dryrun.json
& 'C:\xampp\php\php.exe' scripts/import_dryrun_certificates.php --json `
    > storage\exports\cert-dryrun.json

# Smoke test (assert no DB writes):
& 'C:\xampp\php\php.exe' scripts/smoke_phase8.php
```

Sumber default: `data-lengkap-sertifikat.csv` di root project. Override
dengan `--source=path\to\file.csv` jika perlu. Apply-mode importer
belum ada (tertahan menunggu approval owner atas mapping). Lihat
`docs/import-mapping.md`.

## Smoke Test Phase 7 (Export Excel + PDF)

Validasi semua role login bisa download `.xlsx` dan `.pdf`, content-type
benar, filename mengandung tahun/bulan, urutan kolom Excel cocok dengan
`docs/export-report-spec.md`, row count cocok dengan SQL oracle, filter
diteruskan ke export, `export_jobs` + `audit_logs export.create` tercatat,
dan empty-period tetap menghasilkan file (header saja, 0 data row).

Prasyarat: `composer.phar require phpoffice/phpspreadsheet dompdf/dompdf`
sudah dijalankan, ekstensi PHP `ext-gd` dan `ext-zip` aktif di
`C:\xampp\php\php.ini` (uncomment baris `extension=gd` dan
`extension=zip`, restart PHP).

```powershell
$proc = Start-Process -FilePath 'C:\xampp\php\php.exe' `
    -ArgumentList "-S","127.0.0.1:8771","-t","public" `
    -WindowStyle Hidden -PassThru
Start-Sleep -Milliseconds 1200
& 'C:\xampp\php\php.exe' scripts/smoke_phase7.php
Stop-Process -Id $proc.Id -Force
```

File hasil export disimpan di `storage/exports/` dengan nama
`Support_Maintenance_Report_{YYYY}-{MM}_{YYYYMMDD-HHMMSS}.{xlsx,pdf}`.
Folder ini sudah ada di `.gitignore` (kecuali `.gitkeep`).

## Smoke Test Phase 6 (Dashboard + Reports + Error Pages)

Validasi ringkasan dashboard cocok dengan SQL count, monthly report dengan
filter dan periode kosong, serta error pages 403/404/419 ter-render lewat
layout aplikasi.

```powershell
$proc = Start-Process -FilePath 'C:\xampp\php\php.exe' `
    -ArgumentList "-S","127.0.0.1:8770","-t","public" `
    -WindowStyle Hidden -PassThru
Start-Sleep -Milliseconds 800
& 'C:\xampp\php\php.exe' scripts/smoke_phase6.php
Stop-Process -Id $proc.Id -Force
```

Skrip ini self-bootstrap user `admin@itportal.local`,
`viewer@itportal.local`, dan `it@itportal.local` jika belum ada. Output
diakhiri `ALL PASSED`. Periksa `storage/logs/php-error.log` tetap kosong.

## Smoke Test Phase 5 (Master Data)

Tes CRUD dealer + item, role guard admin-only, dan integrasi dengan ticket
form (inactive tidak muncul di select baru, tapi ticket lama tetap bisa
ditampilkan).

```powershell
$proc = Start-Process -FilePath 'C:\xampp\php\php.exe' `
    -ArgumentList "-S","127.0.0.1:8769","-t","public" `
    -WindowStyle Hidden -PassThru
Start-Sleep -Milliseconds 800
& 'C:\xampp\php\php.exe' scripts/smoke_phase5.php
Stop-Process -Id $proc.Id -Force
```

Skrip otomatis membuat user test:
- `viewer@itportal.local` (password `viewer123`)
- `it@itportal.local` (password `itstaff123`)

Output diakhiri `ALL PASSED`.

## Smoke Test Phase 4 (Tickets)

Pastikan migrate + seed sudah jalan, dan ada admin user. Lalu:

```powershell
& 'C:\xampp\php\php.exe' scripts/seed.php dev_dealers   # 3 dealer demo
$proc = Start-Process -FilePath 'C:\xampp\php\php.exe' `
    -ArgumentList "-S","127.0.0.1:8768","-t","public" `
    -WindowStyle Hidden -PassThru
Start-Sleep -Milliseconds 800
& 'C:\xampp\php\php.exe' scripts/smoke_phase4.php
Stop-Process -Id $proc.Id -Force
```

Harapan: `ALL PASSED`. Skrip otomatis membuat user `viewer@itportal.local`
(password `viewer123`) untuk uji read-only.

## Smoke Test Phase 3 (Auth)

Tes login/logout/CSRF/role guard otomatis lewat cURL:

```powershell
# Pastikan ada admin user dulu, lalu:
$proc = Start-Process -FilePath 'C:\xampp\php\php.exe' `
    -ArgumentList "-S","127.0.0.1:8767","-t","public" `
    -WindowStyle Hidden -PassThru
Start-Sleep -Milliseconds 800
& 'C:\xampp\php\php.exe' scripts/smoke_phase3.php
Stop-Process -Id $proc.Id -Force
```

Output yang diharapkan diakhiri `ALL PASSED`.

Setelah migrate, `GET /health` harus menunjukkan `"db":"ok"`.

## Setelah Composer Tersedia

```powershell
composer install
```

`bootstrap.php` otomatis memilih `vendor/autoload.php` kalau ada,
sehingga setup tanpa-composer tetap kompatibel.

## Catatan Keamanan

- Jangan commit `.env`.
- Production wajib `APP_DEBUG=false`.
- Selalu pakai prepared statement (PDO).
- Semua form POST harus ada `<?= csrf_field() ?>`.
