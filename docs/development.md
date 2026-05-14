# Development

## Setup

Expected commands after implementation:

```bash
composer install
cp .env.example .env
php scripts/migrate.php
php scripts/seed.php
php -S 127.0.0.1:8000 -t public
```

## Coding Rules

- PHP 8.2+ syntax.
- PSR-4 autoload.
- Clear namespaces under `App\`.
- Controller methods stay short.
- Service owns business rules.
- Repository owns SQL.
- View has no SQL.
- Escape output.

## Documentation Rules

Update:

- routes: `docs/routes-and-controllers.md`
- schema: `docs/data-model.md`
- export: `docs/export-report-spec.md`
- config: `docs/configuration.md`
- tasks: `docs/implementation-checklist.md`
