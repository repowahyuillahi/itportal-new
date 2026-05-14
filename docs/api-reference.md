# API And Action Reference

ITPortal V1 is a server-rendered PHP app. Most interactions are page routes and
form actions, not a separate JSON API.

This document defines the public interface between UI forms, controllers, and
exports.

## Auth

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/login` | Show login page |
| POST | `/login` | Authenticate user |
| POST | `/logout` | End session |

## Dashboard

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/dashboard` | Monthly summary dashboard |

Query:

- `month`
- `year`

## Tickets

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/tickets` | List and filter tickets |
| GET | `/tickets/create` | Show ticket form |
| POST | `/tickets` | Create ticket |
| GET | `/tickets/{id}` | Ticket detail |
| GET | `/tickets/{id}/edit` | Edit form |
| POST | `/tickets/{id}` | Update ticket |
| POST | `/tickets/{id}/close` | Close ticket |

Filters:

- `month`
- `year`
- `status`
- `dealer_id`
- `item_id`
- `q`
- `page`

## Dealers

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/dealers` | List dealers |
| POST | `/dealers` | Create dealer |
| POST | `/dealers/{id}` | Update dealer |

## Items

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/items` | List items/categories |
| POST | `/items` | Create item/category |
| POST | `/items/{id}` | Update item/category |

## Reports And Export

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/reports/monthly` | Monthly report preview |
| GET | `/exports/monthly/excel` | Download Excel report |
| GET | `/exports/monthly/pdf` | Download PDF report |

Report/export filters:

- `month`
- `year`
- `status`
- `dealer_id`
- `item_id`
- `q`

## Health

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/health` | App health check |

## Form Rules

- All POST actions require login unless explicitly public.
- All POST actions require CSRF token.
- Validation errors redirect back with flash errors and safe old input.
- Successful mutations redirect to the relevant list/detail page.

