# Source Data Import - Mapping Spec (Phase 8 Dry-Run)

This document defines how legacy source files map onto ITPortal canonical
tables. **Phase 8 is dry-run only**: the CLI tools in `scripts/` parse
the source, compare against the live DB, and write a diff report. They
do not insert, update, or delete any rows.

A real import happens only after the data owner reviews the diff report,
approves the mapping, and explicitly runs the importer in apply mode
(planned for a follow-up phase, not Phase 8).

## Source File Inventory

| File | Use in V1 | Phase |
|---|---|---|
| `data-lengkap-sertifikat.csv` | Canonical dealer + DpackWeb certificate list | Phase 8 (dry-run) |
| `0426-WahyuIllahi.pptx` | Stakeholder presentation; not imported | n/a |
| `2026-IT-Monitoring.xlsx` | Operational monitoring logs (CCTV/Server/Radio); not master data | n/a |
| `Weekly Report.xlsx`, `25 Maret 2026.xlsx`, `CCTV Access Control Maret 2026.xlsx`, `Improvement_Report_*.xlsx`, `10-Fauzan-DailyReport.xlsx` | Operational logs / daily reports; not master data | n/a |

Operational log files describe day-to-day check-in status per device,
not entities that ITPortal owns. They will be revisited (or replaced)
by the ticket-based monitoring flow in later phases, not imported.

## `data-lengkap-sertifikat.csv` Columns

The CSV has a UTF-8 BOM on the first column. 14 columns:

| # | Column | Example | Notes |
|---|---|---|---|
| 0 | `area` | `1. PADANG` | Logical area / region. The main dealer row uses `0. MAIN DEALER (GA0701)` (same as branch). |
| 1 | `folder_area` | `1. PADANG` | Filesystem folder under DpackWeb. Same as `area` in observed rows. |
| 2 | `branch` | `SENTRAL YAMAHA (9FP002)` | Dealer name with its `cert_code` in parens. The main dealer row repeats `0. MAIN DEALER (GA0701)`. |
| 3 | `file_p12` | `C:\Portable Apps\...\AP9FP002.P12` | Absolute path to the PKCS#12 certificate file on the operator's laptop. Path is local-only and not portable. |
| 4 | `file_pin` | `C:\...\AP9FP002.PIN` | Path to companion PIN file. |
| 5 | `cert_code` | `AP9FP002`, `APGA0701` | DpackWeb certificate code. Prefix `AP` + dealer-suffix. |
| 6 | `dealer_code` | `9FP002`, `018058`, `GA0701` | Yamaha dealer code. **Authoritative**. Leading zeros must be preserved (treat as string). |
| 7 | `pin` | `tj14apubqfxfv3x1` | DpackWeb PIN value (sensitive). |
| 8 | `subject` | `CN=YDOI, OU=ID, OU=AP9FP002, O=YAMAHA MOTOR, C=JP` | X.509 subject. |
| 9 | `issuer` | `CN=YAMAHA MOTOR Certification Authority5, ...` | X.509 issuer. |
| 10 | `valid_from` | `11/13/2023 7:00 AM` | US-style `M/D/YYYY h:mm AM/PM`. Asia/Jakarta timezone (operator local time, no offset). |
| 11 | `valid_to`   | `1/31/2029 9:59 PM` | Same format as `valid_from`. |
| 12 | `thumbprint_sha1` | `44D503F95F9E...` | 40 hex chars. |
| 13 | `serial_number`  | `20009FC5` | Hex string. |

## Mapping to `dealers`

Live table columns (per `database/migrations/004_create_dealers_table.sql`):
`id, code, name, area, address, pic_name, pic_phone, status, created_at, updated_at`.

| Source column | dealers column | Transform |
|---|---|---|
| `dealer_code` | `code` | Trim. Uppercase. Empty → mark row `unparseable` (cannot uniquely identify). |
| `branch` | `name` | Strip the trailing ` (CODE)` suffix; trim. If the result is empty fall back to the raw branch. |
| `area` | `area` | Trim. The leading `N. ` prefix kept verbatim (sortable and matches operator's filesystem). |
| `file_p12`, `file_pin` | n/a | Ignored - operator-local paths, not portable. |
| n/a | `address` | Not in source. Leave NULL. |
| n/a | `pic_name`, `pic_phone` | Not in source. Leave NULL. |
| n/a | `status` | Default `active` on import. |

**Uniqueness**: `dealers.code` has a `UNIQUE` index. Dry-run uses `code`
as the merge key.

Possible diff verdicts per source row:

- **would_create** - `dealer_code` is non-empty and no live dealer has
  this `code`.
- **would_update** - live dealer with same `code` exists but `name`
  and/or `area` differ from the canonical source.
- **match** - live dealer exists and `name`+`area` already equal the
  canonical source.
- **ambiguous** - the CSV itself contains two rows with the same
  `dealer_code` but different `name`/`area`. Flagged for owner review;
  no merge attempted.
- **unparseable** - `dealer_code` blank or `branch` blank. Dropped from
  the import plan.

## Mapping to Certificates (No Live Table Yet)

There is currently **no `dealer_certificates` table** in the schema. The
certificate dry-run reports the canonical row set and flags structural
gaps:

- Certificates that reference a `dealer_code` not present in `dealers`
  (after the dealer dry-run is applied) → tagged `orphan_certificate`.
- Multiple rows with the same `cert_code` → `duplicate_cert_code`.
- Multiple certificates per dealer → `multiple_certs_per_dealer`
  (informational; not necessarily an error).
- Rows whose `valid_to` is in the past → `expired`.
- Rows whose `valid_to` is within 60 days → `expiring_soon`.

The dry-run prints a proposed `dealer_certificates` table schema in
its report header so the owner can confirm the columns before any
migration is committed.

## Sensitive Data Handling

`pin`, `file_p12`, `file_pin`, `thumbprint_sha1`, and `serial_number`
columns are sensitive:

- Dry-run reports **never** print full PIN values - only the first 2
  and last 2 characters with a `***` middle.
- `file_p12` / `file_pin` paths are printed only when explicitly
  requested via `--show-paths` (off by default) since they leak the
  operator's local directory layout.
- Full thumbprint / serial are printed because they are public
  certificate identifiers, not secrets.

## How to Run the Dry-Run

From the project root:

```powershell
# 1. Dealers dry-run (text report on stdout).
& 'C:\xampp\php\php.exe' scripts/import_dryrun_dealers.php

# Optional: write a JSON copy for the owner to review:
& 'C:\xampp\php\php.exe' scripts/import_dryrun_dealers.php --json > storage\exports\dealer-dryrun.json

# 2. Certificates dry-run.
& 'C:\xampp\php\php.exe' scripts/import_dryrun_certificates.php
```

Default source: `data-lengkap-sertifikat.csv` in the project root.
Override with `--source=path\to\file.csv` if needed.

The smoke test `scripts/smoke_phase8.php` runs both dry-runs back-to-back
and asserts that no rows in `dealers`, `dealer_certificates` (when it
exists), `tickets`, `users`, `items`, or `audit_logs` change as a result.

## Approval Workflow Before Apply

1. Owner opens the JSON / text report.
2. Confirms each `would_create` row makes sense.
3. Confirms each `would_update` is the desired direction (CSV → DB).
4. Resolves any `ambiguous` row by editing the source CSV.
5. Approves the mapping in writing. **Only then** a follow-up phase
   builds the apply-mode importer with audit logging
   (`dealer.import.create`, `dealer.import.update`).

No automatic re-running of the import on a schedule. No silent updates.
