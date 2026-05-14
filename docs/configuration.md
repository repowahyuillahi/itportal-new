# Configuration

Use `.env` for local/production configuration.

## Required

```text
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=itportal
DB_USERNAME=root
DB_PASSWORD=

SESSION_NAME=itportal_session
SESSION_LIFETIME_MINUTES=480

STORAGE_PATH=storage
EXPORT_PATH=storage/exports
UPLOAD_PATH=storage/uploads
MAX_UPLOAD_MB=5
```

## Production

Production must use:

```text
APP_ENV=production
APP_DEBUG=false
```

## Notes

- Do not commit real `.env`.
- Keep `.env.example` updated.
- All paths should be relative to project root unless explicitly absolute.

