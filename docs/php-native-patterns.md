# PHP Native Patterns

## Style

PHP native bukan berarti satu file besar. Gunakan struktur sederhana:

- Controller untuk request/response.
- Service untuk business rules.
- Repository untuk SQL.
- View untuk HTML.
- Core helper untuk router, session, CSRF, DB, auth.

## Composer

Composer digunakan untuk:

- PSR-4 autoload;
- PhpSpreadsheet;
- Dompdf atau mPDF;
- Dotenv opsional.

Jangan menambahkan framework besar.

## Database

Use PDO:

```php
$stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id');
$stmt->execute(['id' => $id]);
```

Rules:

- No string-concatenated SQL with user input.
- Repositories return arrays or simple DTO-like arrays.
- Transactions for multi-table writes.

## Views

Views are PHP templates:

```php
<?= e($ticket['dealer_name']) ?>
```

All output must be escaped by default using helper `e()`.

## Forms

All POST forms include CSRF:

```php
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
```

Validation errors return to form with old input and flash message.

## Sessions

Use secure PHP sessions:

- regenerate ID after login;
- httpOnly cookie;
- secure cookie in production;
- sameSite Lax;
- session timeout.

## Lead Time

Lead time is calculated in `TicketService`, not in view:

```text
lead_time_seconds = finished_at - started_at
```

Display helper formats it as `HH:mm:ss`.

