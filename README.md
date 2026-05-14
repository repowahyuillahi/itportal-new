# ITPortal

ITPortal adalah aplikasi internal untuk kerja harian IT support dan pembuatan
Support Maintenance Report bulanan.

Project ini sengaja diarahkan ke **PHP native modular** supaya ringan, mudah
dipahami, mudah dideploy di aaPanel, dan tetap rapi untuk dikembangkan.

## Stack Final

- App: PHP native modular, tanpa Laravel/full-stack framework
- Database: MySQL atau MariaDB
- UI: server-rendered PHP views, responsive CSS, minimal vanilla JavaScript
- Export: Excel dan PDF
- Dependencies: Composer hanya untuk autoload dan library kecil
- Deployment: VPS/aaPanel/Nginx atau Apache

Library yang diperbolehkan:

- `phpoffice/phpspreadsheet` untuk Excel
- `dompdf/dompdf` atau `mpdf/mpdf` untuk PDF
- `vlucas/phpdotenv` opsional untuk `.env`

## Scope V1

- Login dan role internal
- Dashboard ringkasan bulanan
- Input dan kelola ticket/laporan IT
- Kelola dealer
- Kelola item/kategori
- Filter report bulanan
- Export Excel dan PDF seperti format meeting
- Mobile-friendly form dan ticket list

Tidak masuk V1:

- Dealer login sendiri
- Monitoring server/jaringan otomatis
- Export PPT
- WhatsApp/email notification
- SLA kompleks

## Bahan Referensi

- `0426-WahyuIllahi.pptx` adalah bahan meeting sebelumnya.
- Gambar report dari user menjadi acuan format export `Support Maintenance
  Report`.

## Documentation Map

| Document | Purpose |
|----------|---------|
| [CLAUDE.md](CLAUDE.md) | Instruksi utama untuk Claude Opus 4.7 |
| [.claude/skills/itportal/SKILL.md](.claude/skills/itportal/SKILL.md) | Skill khusus project |
| [docs/ai-agent-guide.md](docs/ai-agent-guide.md) | Prompt siap pakai untuk Claude |
| [docs/product-vision.md](docs/product-vision.md) | Visi produk dan MVP |
| [docs/requirements.md](docs/requirements.md) | Requirements produk dan behavior |
| [docs/design-decisions.md](docs/design-decisions.md) | Keputusan arsitektur utama |
| [docs/architecture.md](docs/architecture.md) | Arsitektur PHP native |
| [docs/php-native-patterns.md](docs/php-native-patterns.md) | Struktur folder dan pola coding |
| [docs/frontend.md](docs/frontend.md) | Rencana UI desktop/mobile |
| [docs/data-model.md](docs/data-model.md) | Struktur tabel MySQL/MariaDB |
| [docs/api-reference.md](docs/api-reference.md) | Route/action interface untuk form dan export |
| [docs/routes-and-controllers.md](docs/routes-and-controllers.md) | Route, controller, dan flow request |
| [docs/export-report-spec.md](docs/export-report-spec.md) | Format Excel/PDF meeting report |
| [docs/security.md](docs/security.md) | Keamanan aplikasi |
| [docs/security-checklist.md](docs/security-checklist.md) | Checklist keamanan |
| [docs/deployment.md](docs/deployment.md) | Deployment aaPanel/VPS |
| [docs/configuration.md](docs/configuration.md) | Environment/config |
| [docs/development.md](docs/development.md) | Cara development |
| [docs/implementation-plan.md](docs/implementation-plan.md) | Fase kerja Claude |
| [docs/implementation-checklist.md](docs/implementation-checklist.md) | Checklist implementasi |
| [docs/source-materials.md](docs/source-materials.md) | Bahan Excel/CSV/PPTX dan aturan import |
| [docs/roadmap.md](docs/roadmap.md) | Roadmap |
| [docs/documentation-audit.md](docs/documentation-audit.md) | Kesiapan docs dan gap |

## Non-Negotiables

- Jangan pakai Laravel, Next.js, React app, atau Go untuk V1.
- Jangan campur semua logic di satu file PHP.
- Gunakan struktur modular: controller, service, repository, view.
- Gunakan PDO prepared statements.
- Lead time dihitung di backend.
- Mobile harus nyaman untuk input dan baca ticket.
- Export Excel/PDF harus mengikuti `docs/export-report-spec.md`.
- Docs harus diupdate jika schema, route, config, atau arsitektur berubah.
