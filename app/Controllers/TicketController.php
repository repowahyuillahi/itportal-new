<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\DealerRepository;
use App\Repositories\ItemRepository;
use App\Repositories\TicketRepository;
use App\Services\TicketService;

/**
 * TicketController - Phase 4.
 *
 * Mutating actions (create/store/edit/update/close) are gated by
 * RoleMiddleware::only(['admin','it_staff']) at the route layer.
 */
final class TicketController
{
    private TicketService $service;
    private TicketRepository $tickets;
    private DealerRepository $dealers;
    private ItemRepository $items;

    public function __construct(
        ?TicketService    $service = null,
        ?TicketRepository $tickets = null,
        ?DealerRepository $dealers = null,
        ?ItemRepository   $items   = null,
    ) {
        $this->service = $service ?? new TicketService();
        $this->tickets = $tickets ?? new TicketRepository();
        $this->dealers = $dealers ?? new DealerRepository();
        $this->items   = $items   ?? new ItemRepository();
    }

    public function index(Request $request): string
    {
        $filters = $this->extractFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->tickets->paginate($filters, $page, 20);

        return Response::html(View::render('tickets/index', [
            'title'   => 'Tickets - ITPortal',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'pages'   => $result['pages'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'dealers' => $this->dealers->listActive(),
            'items'   => $this->items->listActive(),
            'statuses' => TicketService::STATUSES,
            'canMutate' => Auth::hasAnyRole(['admin', 'it_staff']),
        ]));
    }

    public function create(Request $request): string
    {
        return Response::html(View::render('tickets/form', [
            'title' => 'Buat Ticket - ITPortal',
            'mode' => 'create',
            'ticket' => $this->defaultTicket(),
            'dealers' => $this->dealers->listActive(),
            'items' => $this->items->listActive(),
            'statuses' => TicketService::STATUSES,
            'errors' => $this->popErrors(),
        ]));
    }

    public function store(Request $request): string
    {
        $input = $this->collectFormInput($request);
        $result = $this->service->create($input, $request);
        if (!$result['ok']) {
            $this->flashErrors($result['errors'] ?? [], $input);
            Session::flash('error', 'Periksa kembali isian form.');
            return Response::redirect('/tickets/create');
        }
        Session::flash('success', 'Ticket berhasil dibuat.');
        return Response::redirect('/tickets/' . ($result['id'] ?? 0));
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $ticket = $this->tickets->findById($id);
        if ($ticket === null) {
            return Response::errorPage(404, '404 Not Found', 'Ticket tidak ditemukan.');
        }
        return Response::html(View::render('tickets/show', [
            'title' => 'Ticket ' . ($ticket['ticket_number'] ?? '') . ' - ITPortal',
            'ticket' => $ticket,
            'canMutate' => Auth::hasAnyRole(['admin', 'it_staff']),
        ]));
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $ticket = $this->tickets->findById($id);
        if ($ticket === null) {
            return Response::errorPage(404, '404 Not Found', 'Ticket tidak ditemukan.');
        }
        return Response::html(View::render('tickets/form', [
            'title' => 'Edit ' . ($ticket['ticket_number'] ?? '') . ' - ITPortal',
            'mode' => 'edit',
            'ticket' => $ticket,
            'dealers' => $this->dealers->listActive(),
            'items' => $this->items->listActive(),
            'statuses' => TicketService::STATUSES,
            'errors' => $this->popErrors(),
        ]));
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $input = $this->collectFormInput($request);
        $result = $this->service->update($id, $input, $request);
        if (!$result['ok']) {
            $this->flashErrors($result['errors'] ?? [], $input);
            Session::flash('error', 'Periksa kembali isian form.');
            return Response::redirect('/tickets/' . $id . '/edit');
        }
        Session::flash('success', 'Ticket berhasil diperbarui.');
        return Response::redirect('/tickets/' . $id);
    }

    /** @param array<string,string> $params */
    public function close(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $result = $this->service->close($id, $request);
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? 'Gagal close ticket.');
            return Response::redirect('/tickets/' . $id);
        }
        Session::flash('success', 'Ticket sudah ditutup.');
        return Response::redirect('/tickets/' . $id);
    }

    // ---------- helpers ----------

    /** @return array<string, mixed> */
    private function extractFilters(Request $request): array
    {
        return [
            'month'     => $request->query('month'),
            'year'      => $request->query('year'),
            'status'    => $request->query('status'),
            'dealer_id' => $request->query('dealer_id'),
            'item_id'   => $request->query('item_id'),
            'q'         => $request->query('q'),
        ];
    }

    /** @return array<string, mixed> */
    private function collectFormInput(Request $request): array
    {
        return [
            'status'           => $request->post('status', 'open'),
            'report_date'      => $request->post('report_date', ''),
            'reporter_name'    => $request->post('reporter_name', ''),
            'dealer_id'        => $request->post('dealer_id', ''),
            'item_id'          => $request->post('item_id', ''),
            'initial_report'   => $request->post('initial_report', ''),
            'checking_notes'   => $request->post('checking_notes', ''),
            'solution'         => $request->post('solution', ''),
            'started_at'       => $request->post('started_at', ''),
            'finished_at'      => $request->post('finished_at', ''),
            'assigned_user_id' => $request->post('assigned_user_id', ''),
        ];
    }

    /** @return array<string, mixed> */
    private function defaultTicket(): array
    {
        $now = date('Y-m-d\TH:i');
        return [
            'id' => 0,
            'status' => 'open',
            'report_date' => date('Y-m-d'),
            'reporter_name' => '',
            'dealer_id' => '',
            'item_id' => '',
            'initial_report' => '',
            'checking_notes' => '',
            'solution' => '',
            'started_at' => $now,
            'finished_at' => '',
            'assigned_user_id' => '',
        ];
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    private function flashErrors(array $errors, array $input): void
    {
        Session::flash('_errors', json_encode($errors, JSON_UNESCAPED_UNICODE) ?: '{}');
        // Don't echo password-class fields; tickets have none, so dump all.
        Session::flash('_old', json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /** @return array<string, string> */
    private function popErrors(): array
    {
        $bag = Session::flash('_errors');
        if ($bag === null) {
            return [];
        }
        $arr = json_decode($bag, true);
        return is_array($arr) ? $arr : [];
    }
}
