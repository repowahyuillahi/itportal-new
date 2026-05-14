# Routes And Controllers

## Page Routes

| Method | Route | Controller | Role |
|--------|-------|------------|------|
| GET | `/login` | `AuthController@showLogin` | guest |
| POST | `/login` | `AuthController@login` | guest |
| POST | `/logout` | `AuthController@logout` | user |
| GET | `/dashboard` | `DashboardController@index` | user |
| GET | `/tickets` | `TicketController@index` | user |
| GET | `/tickets/create` | `TicketController@create` | it/admin |
| POST | `/tickets` | `TicketController@store` | it/admin |
| GET | `/tickets/{id}` | `TicketController@show` | user |
| GET | `/tickets/{id}/edit` | `TicketController@edit` | it/admin |
| POST | `/tickets/{id}` | `TicketController@update` | it/admin |
| POST | `/tickets/{id}/close` | `TicketController@close` | it/admin |
| GET | `/dealers` | `DealerController@index` | user |
| POST | `/dealers` | `DealerController@store` | admin |
| POST | `/dealers/{id}` | `DealerController@update` | admin |
| GET | `/items` | `ItemController@index` | user |
| POST | `/items` | `ItemController@store` | admin |
| POST | `/items/{id}` | `ItemController@update` | admin |
| GET | `/reports/monthly` | `ReportController@monthly` | user |
| GET | `/exports/monthly/excel` | `ExportController@monthlyExcel` | user |
| GET | `/exports/monthly/pdf` | `ExportController@monthlyPdf` | user |
| GET | `/health` | `HealthController@health` | public |

## Filters

Ticket/report filters:

- `month`
- `year`
- `status`
- `dealer_id`
- `item_id`
- `q`
- `page`

## Middleware (Phase 3)

Routes use a per-route middleware pipeline registered in `routes.php`:

| Middleware | Job |
|------------|-----|
| `CsrfMiddleware` | Verify `_csrf` field (or `X-CSRF-Token` header) on POST. Returns `419` on mismatch. |
| `GuestMiddleware` | Redirect authenticated users away from `/login` to `/dashboard`. |
| `AuthMiddleware` | Redirect unauthenticated users to `/login` with a flash message. |
| `RoleMiddleware::only([...])` | Allow only listed roles. Assumes `AuthMiddleware` ran first. Returns `403` otherwise. |

Currently wired:

| Method | Route | Middleware |
|--------|-------|-----------|
| GET  | `/health`              | (none, public) |
| GET  | `/`                    | (none, redirects) |
| GET  | `/login`               | `GuestMiddleware` |
| POST | `/login`               | `CsrfMiddleware`, `GuestMiddleware` |
| POST | `/logout`              | `CsrfMiddleware`, `AuthMiddleware` |
| GET  | `/dashboard`           | `AuthMiddleware` |
| GET  | `/tickets`             | `AuthMiddleware` |
| GET  | `/tickets/create`      | `AuthMiddleware`, `RoleMiddleware::only(['admin','it_staff'])` |
| POST | `/tickets`             | `CsrfMiddleware`, `AuthMiddleware`, role admin/it_staff |
| GET  | `/tickets/{id}`        | `AuthMiddleware` |
| GET  | `/tickets/{id}/edit`   | `AuthMiddleware`, role admin/it_staff |
| POST | `/tickets/{id}`        | `CsrfMiddleware`, `AuthMiddleware`, role admin/it_staff |
| POST | `/tickets/{id}/close`  | `CsrfMiddleware`, `AuthMiddleware`, role admin/it_staff |
| GET  | `/dealers`             | `AuthMiddleware` |
| GET  | `/dealers/create`      | `AuthMiddleware`, `RoleMiddleware::only(['admin'])` |
| POST | `/dealers`             | `CsrfMiddleware`, `AuthMiddleware`, role admin |
| GET  | `/dealers/{id}/edit`   | `AuthMiddleware`, role admin |
| POST | `/dealers/{id}`        | `CsrfMiddleware`, `AuthMiddleware`, role admin |
| POST | `/dealers/{id}/status` | `CsrfMiddleware`, `AuthMiddleware`, role admin |
| GET  | `/items`               | `AuthMiddleware` |
| GET  | `/items/create`        | `AuthMiddleware`, role admin |
| POST | `/items`               | `CsrfMiddleware`, `AuthMiddleware`, role admin |
| GET  | `/items/{id}/edit`     | `AuthMiddleware`, role admin |
| POST | `/items/{id}`          | `CsrfMiddleware`, `AuthMiddleware`, role admin |
| POST | `/items/{id}/status`   | `CsrfMiddleware`, `AuthMiddleware`, role admin |
| GET  | `/reports/monthly`     | `AuthMiddleware` (read-only preview; export buttons placeholder) |

Phase 7 will add `/exports/monthly/{xlsx,pdf}`.

## Error Pages (Phase 6)

All non-redirect error responses (403/404/419) flow through
`Response::errorPage($status, $heading, $message)` which renders
`resources/views/errors/generic.php` inside `layouts/app`. Sites that
emit error pages:

- `Router::dispatch` -> `404` for unknown routes.
- `RoleMiddleware::handle` -> `403` for forbidden roles.
- `CsrfMiddleware::handle` -> `419` for invalid/missing CSRF token.
- `*Controller::edit/show/toggleStatus` -> `404` for missing IDs.

## Master Data Conventions (Phase 5)

- **Soft toggle only** — dealers and items are never deleted. Use the
  `POST /{resource}/{id}/status` endpoint (admin-only, CSRF-protected) to
  flip between `active` and `inactive`. Provide `status=active|inactive` in
  the form body to set explicitly; otherwise the current value is flipped.
- **Ticket form selectors** call `listActive()` on the repositories so
  inactive rows disappear from new tickets while still rendering on
  existing ticket details (which join through plain `INNER JOIN`).
- **Uniqueness**: dealer `code` is unique only when present (NULL allowed
  multiple times). Item `slug` is always unique; auto-generated from
  `name` when omitted (lowercase ascii + hyphens, suffixed `-2`, `-3`, ...
  to deduplicate). Slug pattern: `^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$`.

## Ticket Number Format

`TKT-YYYYMM-####` where `YYYYMM` comes from `report_date` and `####` is a
zero-padded counter scoped per-month. Generation is delegated to
`TicketRepository::nextTicketNumber()` (queries the latest matching prefix).

## Controller Rule

Controllers should be short:

```text
request -> validate -> service -> view/redirect/download
```

Do not put SQL or export formatting directly in controllers.

