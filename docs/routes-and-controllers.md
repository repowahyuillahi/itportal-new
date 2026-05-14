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
| GET  | `/health`    | (none, public) |
| GET  | `/`          | (none, redirects) |
| GET  | `/login`     | `GuestMiddleware` |
| POST | `/login`     | `CsrfMiddleware`, `GuestMiddleware` |
| POST | `/logout`    | `CsrfMiddleware`, `AuthMiddleware` |
| GET  | `/dashboard` | `AuthMiddleware` |

Phase 4-6 will add `/tickets*`, `/dealers*`, `/items*`, `/reports/monthly`,
`/exports/monthly/*` with `AuthMiddleware` and (for mutating routes) the
appropriate `RoleMiddleware::only(['admin'])` /
`RoleMiddleware::only(['admin', 'it_staff'])`.

## Controller Rule

Controllers should be short:

```text
request -> validate -> service -> view/redirect/download
```

Do not put SQL or export formatting directly in controllers.

