<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\View;
use App\Repositories\ExportRepository;
use App\Repositories\TicketRepository;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * ExportService - generates monthly Excel and PDF report files.
 *
 * Column order is fixed by docs/export-report-spec.md:
 *   1. No
 *   2. Status
 *   3. Tanggal
 *   4. Pelapor
 *   5. Dealer
 *   6. Laporan Awal
 *   7. Pengecekan
 *   8. Solusi
 *   9. Item
 *  10. Waktu Mulai
 *  11. Waktu Selesai
 *  12. Lead Time
 *
 * Files are written under storage/exports/ with a deterministic, server-
 * controlled name and recorded in `export_jobs` for audit.
 */
final class ExportService
{
    public const TYPE_EXCEL = 'monthly_excel';
    public const TYPE_PDF   = 'monthly_pdf';

    public const MAX_ROWS = 5000;

    /** Excel MIME for .xlsx (OOXML). */
    public const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    public const MIME_PDF  = 'application/pdf';

    /** Fixed column headers in spec order. */
    private const COLUMNS = [
        'No',
        'Status',
        'Tanggal',
        'Pelapor',
        'Dealer',
        'Laporan Awal',
        'Pengecekan',
        'Solusi',
        'Item',
        'Waktu Mulai',
        'Waktu Selesai',
        'Lead Time',
    ];

    private TicketRepository $tickets;
    private ExportRepository $jobs;

    public function __construct(?TicketRepository $tickets = null, ?ExportRepository $jobs = null)
    {
        $this->tickets = $tickets ?? new TicketRepository();
        $this->jobs    = $jobs    ?? new ExportRepository();
    }

    /**
     * Generate the Excel file for the given filters.
     *
     * @param array<string, mixed> $filters must include `year` and `month`
     * @return array{path:string, filename:string, content_type:string, rows:int, job_id:int}
     */
    public function buildExcel(array $filters, int $userId): array
    {
        [$year, $month] = $this->resolveYearMonth($filters);
        $rows = $this->tickets->listForReport($filters, self::MAX_ROWS);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheetName = $this->monthName($month) . ' ' . $year;
        // Excel sheet names are limited to 31 chars and forbid : \ / ? * [ ]
        $sheet->setTitle(substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '_', $sheetName) ?? 'Report', 0, 31));

        $title = $this->reportTitle($month, $year);

        // Row 1: title spans all 12 columns.
        $lastCol = 'L'; // 12 columns A..L
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Row 2: blue header.
        $headerRow = 2;
        foreach (self::COLUMNS as $i => $label) {
            $col = $this->colLetter($i + 1);
            $sheet->setCellValue($col . $headerRow, $label);
        }
        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $headerStyle = $sheet->getStyle($headerRange);
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F4E78'); // dark blue
        $headerStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // Data rows start at row 3.
        $dataStart = 3;
        $rowIdx = $dataStart;
        $no = 1;
        $hasData = $rows !== [];
        foreach ($rows as $r) {
            $values = $this->rowToCells($r, $no);
            $no++;
            $colN = 1;
            foreach ($values as $v) {
                $cellCol = $this->colLetter($colN);
                // Force text for the No column so leading zeros are preserved;
                // everything else uses default detection (we already format
                // dates as strings, so PhpSpreadsheet will store strings).
                $sheet->setCellValueExplicit(
                    $cellCol . $rowIdx,
                    (string) $v,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $colN++;
            }
            $rowIdx++;
        }
        $dataEnd = $hasData ? ($rowIdx - 1) : $headerRow;

        // Borders: header always; data rows only if present.
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('BFBFBF');
        if ($hasData) {
            $sheet->getStyle("A{$dataStart}:{$lastCol}{$dataEnd}")
                ->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setRGB('BFBFBF');

            // Wrap text + top-align for the long-text columns (F=Laporan Awal,
            // G=Pengecekan, H=Solusi) across the data rows.
            $sheet->getStyle("F{$dataStart}:H{$dataEnd}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$dataStart}:{$lastCol}{$dataEnd}")
                ->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }

        // Column widths (chars).
        $widths = [
            'A' =>  5,  // No
            'B' => 12,  // Status
            'C' => 12,  // Tanggal
            'D' => 22,  // Pelapor
            'E' => 26,  // Dealer
            'F' => 40,  // Laporan Awal
            'G' => 40,  // Pengecekan
            'H' => 40,  // Solusi
            'I' => 18,  // Item
            'J' => 18,  // Waktu Mulai
            'K' => 18,  // Waktu Selesai
            'L' => 12,  // Lead Time
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth((float) $w);
        }

        // Freeze the title + header (everything from row 3 down scrolls).
        $sheet->freezePane('A' . $dataStart);

        // Auto filter on the header row. Spans header + data; when there
        // are no data rows we still expose the header so the toggle is
        // available without claiming an empty data row exists.
        $autoEnd = $hasData ? $dataEnd : $headerRow;
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$autoEnd}");

        // Page setup: landscape, fit to width, repeat header on print.
        $page = $sheet->getPageSetup();
        $page->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $page->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $page->setFitToWidth(1);
        $page->setFitToHeight(0);
        $page->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow);

        // Write file.
        [$path, $filename] = $this->buildOutputPath($year, $month, 'xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $jobId = $this->jobs->insert(self::TYPE_EXCEL, $filters, $path, $userId);

        return [
            'path'         => $path,
            'filename'     => $filename,
            'content_type' => self::MIME_XLSX,
            'rows'         => count($rows),
            'job_id'       => $jobId,
        ];
    }

    /**
     * Generate the PDF file for the given filters (landscape).
     *
     * @param array<string, mixed> $filters must include `year` and `month`
     * @return array{path:string, filename:string, content_type:string, rows:int, job_id:int}
     */
    public function buildPdf(array $filters, int $userId): array
    {
        [$year, $month] = $this->resolveYearMonth($filters);
        $rows = $this->tickets->listForReport($filters, self::MAX_ROWS);

        // Pre-format rows for the template (so the view stays dumb).
        $tableRows = [];
        $no = 1;
        foreach ($rows as $r) {
            $tableRows[] = $this->rowToCells($r, $no);
            $no++;
        }

        $html = View::render('exports/monthly_pdf', [
            'title'      => $this->reportTitle($month, $year),
            'columns'    => self::COLUMNS,
            'rows'       => $tableRows,
            'period'     => $this->monthName($month) . ' ' . $year,
            'generated'  => date('d/m/Y H:i'),
        ], null);

        $options = new DompdfOptions();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', APP_BASE_PATH);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $output = $dompdf->output();

        [$path, $filename] = $this->buildOutputPath($year, $month, 'pdf');
        file_put_contents($path, $output);

        $jobId = $this->jobs->insert(self::TYPE_PDF, $filters, $path, $userId);

        return [
            'path'         => $path,
            'filename'     => $filename,
            'content_type' => self::MIME_PDF,
            'rows'         => count($rows),
            'job_id'       => $jobId,
        ];
    }

    /**
     * Map a TicketRepository::listForReport row to the spec column order.
     *
     * @param array<string, mixed> $r
     * @return array<int, string>
     */
    private function rowToCells(array $r, int $no): array
    {
        return [
            (string) $no,
            str_replace('_', ' ', (string) $r['status']),
            format_date_id((string) ($r['report_date'] ?? '')),
            (string) ($r['reporter_name'] ?? ''),
            (string) ($r['dealer_name'] ?? ''),
            (string) ($r['initial_report'] ?? ''),
            (string) ($r['checking_notes'] ?? ''),
            (string) ($r['solution'] ?? ''),
            (string) ($r['item_name'] ?? ''),
            format_datetime_id($r['started_at'] ?? null),
            format_datetime_id($r['finished_at'] ?? null),
            format_lead_time(isset($r['lead_time_seconds']) ? (int) $r['lead_time_seconds'] : null),
        ];
    }

    /** @return array{0:int,1:int} */
    private function resolveYearMonth(array $filters): array
    {
        $year  = (int) ($filters['year']  ?? date('Y'));
        $month = (int) ($filters['month'] ?? date('n'));
        if ($year  < 2000 || $year  > 9999) { $year  = (int) date('Y'); }
        if ($month < 1    || $month > 12)   { $month = (int) date('n'); }
        return [$year, $month];
    }

    private function reportTitle(int $month, int $year): string
    {
        return 'Support Maintenance Report ' . $this->monthName($month) . ' ' . $year;
    }

    private function monthName(int $m): string
    {
        $names = ['', 'Januari','Februari','Maret','April','Mei','Juni',
                  'Juli','Agustus','September','Oktober','November','Desember'];
        return $names[$m] ?? (string) $m;
    }

    /**
     * Build the absolute output path under storage/exports and the user-
     * facing download filename. Never derived from user input.
     *
     * @return array{0:string,1:string}
     */
    private function buildOutputPath(int $year, int $month, string $ext): array
    {
        // storage path: if the config returns an absolute path, use it as-is;
        // otherwise resolve it relative to APP_BASE_PATH.
        $base = (string) Config::get('app.storage_path', 'storage');
        if (!preg_match('#^([a-zA-Z]:[\\\\/]|/)#', $base)) {
            $base = APP_BASE_PATH . '/' . ltrim($base, "\\/");
        }
        $dir = rtrim($base, "\\/") . '/exports';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $stamp = date('Ymd-His');
        $monthPad = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        // Server-controlled filename. No path separators allowed.
        $filename = sprintf(
            'Support_Maintenance_Report_%d-%s_%s.%s',
            $year,
            $monthPad,
            $stamp,
            $ext
        );
        return [$dir . '/' . $filename, $filename];
    }

    private function colLetter(int $n): string
    {
        // 1 => A, 26 => Z, 27 => AA, ... (we only need up to L here).
        $letters = '';
        while ($n > 0) {
            $n--;
            $letters = chr(65 + ($n % 26)) . $letters;
            $n = intdiv($n, 26);
        }
        return $letters;
    }
}
