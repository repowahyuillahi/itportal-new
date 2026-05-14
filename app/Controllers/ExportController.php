<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\TicketRepository;
use App\Services\AuditService;
use App\Services\ExportService;

/**
 * ExportController - thin controller for the monthly Excel/PDF download.
 *
 * Authorization: any authenticated user (AuthMiddleware on the route).
 * No CSRF: GET-only download endpoint.
 *
 * Flow per request:
 *   1. Resolve and clamp filters (same shape as /reports/monthly).
 *   2. Count rows; reject with a layout error page if > MAX_ROWS.
 *   3. Delegate file generation to ExportService.
 *   4. Stream the file via Response::download().
 *   5. Audit log `export.create` (best effort; non-blocking).
 */
final class ExportController
{
    private ExportService $exports;
    private TicketRepository $tickets;
    private AuditService $audit;

    public function __construct(
        ?ExportService    $exports = null,
        ?TicketRepository $tickets = null,
        ?AuditService     $audit   = null,
    ) {
        $this->exports = $exports ?? new ExportService();
        $this->tickets = $tickets ?? new TicketRepository();
        $this->audit   = $audit   ?? new AuditService();
    }

    public function monthlyExcel(Request $request): string
    {
        return $this->run($request, 'excel');
    }

    public function monthlyPdf(Request $request): string
    {
        return $this->run($request, 'pdf');
    }

    private function run(Request $request, string $format): string
    {
        $filters = $this->resolveFilters($request);

        $count = $this->tickets->countForReport($filters);
        if ($count > ExportService::MAX_ROWS) {
            return Response::errorPage(
                413,
                'Terlalu Banyak Baris',
                sprintf(
                    'Filter ini menghasilkan %d baris, melebihi batas %d baris untuk satu kali export. '
                    . 'Persempit filter (status, dealer, item, atau pencarian) lalu coba lagi.',
                    $count,
                    ExportService::MAX_ROWS
                )
            );
        }

        $userId = Auth::id() ?? 0;
        try {
            $result = $format === 'excel'
                ? $this->exports->buildExcel($filters, $userId)
                : $this->exports->buildPdf($filters, $userId);
        } catch (\Throwable $e) {
            error_log('Export ' . $format . ' failed: ' . $e->getMessage());
            return Response::errorPage(
                500,
                'Gagal Membuat Export',
                'Terjadi kesalahan saat membuat file export. Detail tercatat di log server.'
            );
        }

        $this->audit->log(
            $userId > 0 ? $userId : null,
            'export.create',
            $request,
            'export_job',
            (string) $result['job_id'],
            null,
            [
                'type'    => $format === 'excel' ? ExportService::TYPE_EXCEL : ExportService::TYPE_PDF,
                'filters' => $filters,
                'rows'    => $result['rows'],
                'file'    => basename($result['path']),
            ],
        );

        return Response::download(
            $result['path'],
            $result['filename'],
            $result['content_type']
        );
    }

    /**
     * Same filter shape as ReportController::monthly().
     *
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $year  = (int) ($request->query('year')  ?? date('Y'));
        $month = (int) ($request->query('month') ?? date('n'));
        if ($year  < 2000 || $year  > 9999) { $year  = (int) date('Y'); }
        if ($month < 1    || $month > 12)   { $month = (int) date('n'); }

        return [
            'month'     => $month,
            'year'      => $year,
            'status'    => $request->query('status'),
            'dealer_id' => $request->query('dealer_id'),
            'item_id'   => $request->query('item_id'),
            'q'         => $request->query('q'),
        ];
    }
}
