<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\TicketRepository;
use App\Services\TicketService;

/**
 * DashboardController - monthly summary cards + top dealer/item + recent
 * tickets. Read-only, all logged-in roles can view.
 */
final class DashboardController
{
    private TicketRepository $tickets;

    public function __construct(?TicketRepository $tickets = null)
    {
        $this->tickets = $tickets ?? new TicketRepository();
    }

    public function index(Request $request): string
    {
        [$year, $month] = $this->resolveMonth($request);

        $summary    = $this->tickets->summaryForMonth($year, $month);
        $topDealers = $this->tickets->topDealersForMonth($year, $month, 5);
        $topItems   = $this->tickets->topItemsForMonth($year, $month, 5);
        $recent     = $this->tickets->recentTicketsForMonth($year, $month, 5);

        return Response::html(View::render('dashboard/index', [
            'title'      => 'Dashboard - ITPortal',
            'user'       => Auth::user() ?? [],
            'year'       => $year,
            'month'      => $month,
            'summary'    => $summary,
            'topDealers' => $topDealers,
            'topItems'   => $topItems,
            'recent'     => $recent,
            'statuses'   => TicketService::STATUSES,
        ]));
    }

    /** @return array{0:int,1:int} [year, month] - clamped to valid range. */
    private function resolveMonth(Request $request): array
    {
        $year  = (int) ($request->query('year')  ?? date('Y'));
        $month = (int) ($request->query('month') ?? date('n'));
        if ($year  < 2000 || $year  > 9999) { $year  = (int) date('Y'); }
        if ($month < 1    || $month > 12)   { $month = (int) date('n'); }
        return [$year, $month];
    }
}
