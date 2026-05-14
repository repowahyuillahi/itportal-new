# Export Report Spec

Report title:

```text
Support Maintenance Report {Month} {Year}
```

## Required Columns

Column order is fixed:

1. `No`
2. `Status`
3. `Tanggal`
4. `Pelapor`
5. `Dealer`
6. `Laporan Awal`
7. `Pengecekan`
8. `Solusi`
9. `Item`
10. `Waktu Mulai`
11. `Waktu Selesai`
12. `Lead Time`

## Formatting

- Timezone: `Asia/Jakarta`
- Date: `DD/MM/YYYY`
- Date time: `DD/MM/YYYY HH:mm`
- Lead time: `HH:mm:ss`
- Default row order: newest first
- Long text wraps in Laporan Awal, Pengecekan, Solusi

## Excel

Use PhpSpreadsheet.

Requirements:

- worksheet name: `{Month} {Year}`
- title row above table
- blue header row
- borders
- wrapped text
- freeze header
- auto filter
- sensible column widths

## PDF

Use Dompdf or mPDF.

Requirements:

- landscape
- title visible
- header repeated if possible
- readable text
- no overlapping text

## Empty Report

If no data:

- Excel still has title and headers
- PDF shows title and empty message

