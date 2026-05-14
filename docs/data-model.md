# Data Model

Database: MySQL/MariaDB.

## Implementation Notes (Phase 2)

- Engine: `InnoDB`, charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
- Status/role columns use MySQL `ENUM` for clarity.
- All foreign-key columns are `BIGINT UNSIGNED` matching `users.id`,
  `dealers.id`, `items.id`, `tickets.id`.
- FK behavior:
  - `dealer_id`, `item_id`, `created_by`: `ON DELETE RESTRICT`
    (cannot delete master row that still owns tickets).
  - `assigned_user_id`, `updated_by`, `closed_by`, `audit_logs.actor_user_id`:
    `ON DELETE SET NULL`.
  - `ticket_attachments.ticket_id`, `sessions.user_id`: `ON DELETE CASCADE`.
- Audit log JSON columns: `before_json`, `after_json`, plus `filters_json`
  on `export_jobs` use native `JSON` type.
- Migrations live in `database/migrations/*.sql`, applied by
  `scripts/migrate.php` and tracked in the `migrations` table.

## `users`

- `id` bigint primary key auto increment
- `name` varchar
- `email` varchar unique
- `password_hash` varchar
- `role` enum/string: `admin`, `it_staff`, `manager`, `viewer`
- `status` enum/string: `active`, `inactive`
- `last_login_at` datetime nullable
- `created_at` datetime
- `updated_at` datetime

## `sessions`

- `id` bigint primary key
- `user_id` bigint
- `session_hash` varchar
- `expires_at` datetime
- `revoked_at` datetime nullable
- `created_at` datetime

## `dealers`

- `id`
- `code` varchar nullable
- `name` varchar
- `area` varchar nullable
- `address` text nullable
- `pic_name` varchar nullable
- `pic_phone` varchar nullable
- `status` enum/string: `active`, `inactive`
- `created_at`
- `updated_at`

## `items`

- `id`
- `name`
- `slug`
- `description` text nullable
- `status` enum/string: `active`, `inactive`
- `sort_order` int
- `created_at`
- `updated_at`

Initial items:

- Hardware
- Software
- Printer
- Network
- Internet
- CCTV
- Server
- Backup
- YDT
- DpackWeb
- Radio
- Zahir
- Fingerprint Absensi
- Kabel Listrik dan Adaptor
- Other

## `tickets`

- `id`
- `ticket_number` varchar unique
- `status`: `open`, `in_progress`, `pending`, `closed`, `cancelled`
- `report_date` date
- `reporter_name` varchar
- `dealer_id` bigint
- `item_id` bigint
- `initial_report` text
- `checking_notes` text nullable
- `solution` text nullable
- `started_at` datetime
- `finished_at` datetime nullable
- `lead_time_seconds` int nullable
- `assigned_user_id` bigint nullable
- `created_by` bigint
- `updated_by` bigint nullable
- `closed_by` bigint nullable
- `closed_at` datetime nullable
- `created_at`
- `updated_at`

Indexes:

- `ticket_number`
- `status`
- `report_date`
- `dealer_id`
- `item_id`
- `(report_date, status)`
- `(dealer_id, report_date)`
- `(item_id, report_date)`

## `ticket_attachments`

- `id`
- `ticket_id`
- `filename_original`
- `storage_path`
- `mime_type`
- `size_bytes`
- `uploaded_by`
- `created_at`

## `export_jobs`

- `id`
- `type`: `monthly_excel`, `monthly_pdf`
- `filters_json`
- `file_path`
- `created_by`
- `created_at`

## `audit_logs`

- `id`
- `actor_user_id`
- `action`
- `resource_type`
- `resource_id`
- `before_json` nullable
- `after_json` nullable
- `ip_hash` nullable
- `user_agent_hash` nullable
- `created_at`

