# Source Materials

The project root may contain Excel, CSV, and PPTX files used as meeting/report
references and future import sources.

Current observed materials:

- `0426-WahyuIllahi.pptx`
- `Weekly Report.xlsx`
- `2026-IT-Monitoring.xlsx`
- `10-Fauzan-DailyReport.xlsx`
- `25 Maret 2026.xlsx`
- `CCTV Access Control Maret 2026.xlsx`
- `Improvement_Report_Monitoring_Radio_Server_CCTV.xlsx`
- `data-lengkap-sertifikat.csv`

The owner may add more files later, including dealer data, DpackWeb data, and
other operational data.

## Rules For Claude

- Treat source files as references/import inputs, not application code.
- Do not hardcode spreadsheet rows into PHP.
- Inspect headers and sample rows before designing an importer.
- Use dry-run import first.
- Produce a mapping report before writing data into MySQL.
- Ask owner only when multiple columns could map to the same field and the
  choice affects real data.

## Expected Import Categories

Potential future imports:

- dealers;
- items/categories;
- certificates;
- DpackWeb users/certificates/status;
- CCTV access/control data;
- IT monitoring notes;
- weekly/daily report rows;
- support maintenance ticket history.

## Import Flow

1. Inspect source file headers.
2. Detect candidate entity: dealer, item, ticket, certificate, DpackWeb, CCTV,
   monitoring.
3. Generate dry-run mapping.
4. Show counts: total rows, valid rows, invalid rows, duplicates.
5. Owner reviews mapping.
6. Import into MySQL.
7. Write import audit log.

