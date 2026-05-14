# Deployment

## Target

Deploy on VPS/aaPanel using PHP, MySQL/MariaDB, and Nginx or Apache.

## Web Root

Point domain/subdomain to:

```text
itportal/public
```

Never point web root to the project root.

## Requirements

- PHP 8.2+
- MySQL/MariaDB
- Composer
- PHP extensions:
  - `pdo_mysql`
  - `mbstring`
  - `zip`
  - `gd` or `imagick` if needed by PDF/image handling

## Storage Permissions

Writable:

- `storage/exports`
- `storage/uploads`
- `storage/logs`

Not web-accessible:

- `.env`
- `app/`
- `config/`
- `database/`
- `storage/`

## Backup

- Daily database backup.
- Backup copied outside app folder.
- Backup uploads/exports if needed.
- Restore test before production use.

## Smoke Test

- `/login` opens.
- Login works.
- Dashboard opens.
- Create ticket works.
- Close ticket calculates lead time.
- Dealer/item CRUD works.
- Excel export downloads.
- PDF export downloads.
- Manager/viewer cannot mutate data.
- Mobile ticket form usable.

