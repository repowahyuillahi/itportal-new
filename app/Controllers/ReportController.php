<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\DealerRepository;
use App\Repositories\ItemRepository;
use App\Repositories\TicketRepository;
use App\Services\TicketService;

/**
 * ReportController - Phase 6 monthly preview only. Export buttons render
 * disabled / placeholder; Phase 7 will activate them.
 */
final class ReportController
{
    private TicketRepository $tickets;
    private DealerRepository $dealers;
    private ItemRepository $items;

    public function __construct(
        ?TicketRepository $tickets = null,
        ?DealerRepository $dealers = null,
        ?ItemRepository   $items   = null,
    ) {
        $this->tickets = $tickets ?? new TicketRepository();
        $this->dealers = $dealers ?? new DealerRepository();
        $this->items   = $items   ?? new ItemRepository();
    }

    public function monthly(Request $request): string
    {
        // Default month/year = current.
        $year  = (int) ($request->query('year')  ?? date('Y'));
        $month = (int) ($request->query('month') ?? date('n'));
        if ($year  < 2000 || $year  > 9999) { $year  = (int) date('Y'); }
        if ($month < 1    || $month > 12)   { $month = (int) date('n'); }

        $filters = [
            'month'     => $month,
            'year'      => $year,
            'status'    => $request->query('status'),
            'dealer_id' => $request->query('dealer_id'),
            'item_id'   => $request->query('item_id'),
            'q'         => $request->query('q'),
        ];

        $rows    = $this->tickets->listForReport($filters, 500);
        $summary = $this->tickets->summaryForMonth($year, $month);

        // Dealer/item options: include inactive so existing report rows can
        // still be filtered by a dealer/item that became inactive later.
        $dealers = $this->dealers->listAll();
        $items   = $this->items->listAll();

        return Response::html(View::render('reports/monthly', [
            'title'    => 'Laporan Bulanan - ITPortal',
            'rows'     => $rows,
            'summary'  => $summary,
            'filters'  => $filters,
            'year'     => $year,
            'month'    => $month,
            'dealers'  => $dealers,
            'items'    => $items,
            'statuses' => TicketService::STATUSES,
            'maxRows'  => 500,
        ]));
    }
}
