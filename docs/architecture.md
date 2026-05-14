# Architecture

## Overview

```text
Browser
  -> Nginx/Apache/aaPanel
  -> public/index.php
  -> Router + Middleware
  -> Controller
  -> Service
  -> Repository
  -> MySQL/MariaDB
```

## Stack

- PHP native modular.
- MySQL/MariaDB.
- Server-rendered PHP views.
- Responsive CSS.
- Minimal vanilla JavaScript.
- Composer autoload.
- PhpSpreadsheet for Excel.
- Dompdf or mPDF for PDF.

## Folder Layout

```text
itportal/
  public/
    index.php
    assets/
      css/app.css
      js/app.js
  app/
    Core/
      Router.php
      Request.php
      Response.php
      Database.php
      Session.php
      View.php
      Csrf.php
      Auth.php
    Controllers/
      AuthController.php
      DashboardController.php
      TicketController.php
      DealerController.php
      ItemController.php
      ReportController.php
      ExportController.php
    Services/
      TicketService.php
      ReportService.php
      ExportService.php
      AuditService.php
    Repositories/
      TicketRepository.php
      DealerRepository.php
      ItemRepository.php
      UserRepository.php
      ExportRepository.php
      AuditRepository.php
    Middleware/
      AuthMiddleware.php
      RoleMiddleware.php
      CsrfMiddleware.php
    Helpers/
      date.php
      escape.php
  resources/
    views/
      layouts/
      auth/
      dashboard/
      tickets/
      dealers/
      items/
      reports/
  database/
    migrations/
    seeders/
  storage/
    exports/
    uploads/
    logs/
  config/
    app.php
    database.php
  docs/
```

## Routing

All requests enter through `public/index.php`.

Use clean URLs:

```text
/login
/logout
/dashboard
/tickets
/tickets/create
/tickets/{id}
/tickets/{id}/edit
/tickets/{id}/close
/dealers
/items
/reports/monthly
/exports/monthly/excel
/exports/monthly/pdf
/health
```

## Data Flow

Ticket create:

```text
Form POST -> CsrfMiddleware -> AuthMiddleware -> TicketController
  -> TicketService -> TicketRepository -> MySQL -> redirect with flash
```

Export:

```text
Report filter -> ExportController -> ReportService -> ExportService
  -> file in storage/exports -> download response
```

## Principles

- No business logic in views.
- No SQL in controllers.
- No direct `$_POST` usage outside Request/validation layer.
- Escape output in views.
- Use PDO prepared statements.
- Keep helpers small and obvious.

