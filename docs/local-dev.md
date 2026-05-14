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
