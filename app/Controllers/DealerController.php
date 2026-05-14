<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\DealerRepository;
use App\Services\DealerService;

/**
 * DealerController - Phase 5.
 *
 * Read access for any logged-in user. Mutating actions are gated by
 * RoleMiddleware::only(['admin']) at the route layer.
 */
final class DealerController
{
    private DealerService $service;
    private DealerRepository $dealers;

    public function __construct(?DealerService $service = null, ?DealerRepository $dealers = null)
    {
        $this->service = $service ?? new DealerService();
        $this->dealers = $dealers ?? new DealerRepository();
    }

    public function index(Request $request): string
    {
        $filters = [
            'status' => $request->query('status'),
            'q'      => $request->query('q'),
        ];
        $rows = $this->dealers->listAll($filters);
        return Response::html(View::render('dealers/index', [
            'title'     => 'Dealers - ITPortal',
            'rows'      => $rows,
            'filters'   => $filters,
            'statuses'  => DealerService::STATUSES,
            'canMutate' => Auth::hasAnyRole(['admin']),
        ]));
    }

    public function create(Request $request): string
    {
        return Response::html(View::render('dealers/form', [
            'title'    => 'Buat Dealer - ITPortal',
            'mode'     => 'create',
            'dealer'   => $this->defaultDealer(),
            'statuses' => DealerService::STATUSES,
            'errors'   => $this->popErrors(),
        ]));
    }

    public function store(Request $request): string
    {
        $input = $this->collect($request);
        $result = $this->service->create($input, $request);
        if (!$result['ok']) {
            $this->flashErrors($result['errors'] ?? [], $input);
            Session::flash('error', 'Periksa kembali isian form.');
            return Response::redirect('/dealers/create');
        }
        Session::flash('success', 'Dealer berhasil dibuat.');
        return Response::redirect('/dealers');
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $dealer = $this->dealers->findById($id);
        if ($dealer === null) {
            return Response::errorPage(404, '404 Not Found', 'Dealer tidak ditemukan.');
        }
        return Response::html(View::render('dealers/form', [
            'title'    => 'Edit Dealer - ITPortal',
            'mode'     => 'edit',
            'dealer'   => $dealer,
            'statuses' => DealerService::STATUSES,
            'errors'   => $this->popErrors(),
        ]));
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $input = $this->collect($request);
        $result = $this->service->update($id, $input, $request);
        if (!$result['ok']) {
            $this->flashErrors($result['errors'] ?? [], $input);
            Session::flash('error', 'Periksa kembali isian form.');
            return Response::redirect('/dealers/' . $id . '/edit');
        }
        Session::flash('success', 'Dealer berhasil diperbarui.');
        return Response::redirect('/dealers');
    }

    /** @param array<string,string> $params */
    public function toggleStatus(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $current = $this->dealers->findById($id);
        if ($current === null) {
            return Response::errorPage(404, '404 Not Found', 'Dealer tidak ditemukan.');
        }
        // Allow explicit override via form field, else flip current.
        $requested = (string) $request->post('status', '');
        if ($requested === '') {
            $requested = ((string) $current['status']) === 'active' ? 'inactive' : 'active';
        }
        $result = $this->service->setStatus($id, $requested, $request);
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? 'Gagal mengubah status.');
        } else {
            Session::flash('success', 'Status dealer diubah ke ' . ($result['status'] ?? '?') . '.');
        }
        return Response::redirect('/dealers');
    }

    // ---------- helpers ----------

    /** @return array<string, mixed> */
    private function collect(Request $request): array
    {
        return [
            'code'      => $request->post('code', ''),
            'name'      => $request->post('name', ''),
            'area'      => $request->post('area', ''),
            'address'   => $request->post('address', ''),
            'pic_name'  => $request->post('pic_name', ''),
            'pic_phone' => $request->post('pic_phone', ''),
            'status'    => $request->post('status', 'active'),
        ];
    }

    /** @return array<string, mixed> */
    private function defaultDealer(): array
    {
        return [
            'id' => 0,
            'code' => '',
            'name' => '',
            'area' => '',
            'address' => '',
            'pic_name' => '',
            'pic_phone' => '',
            'status' => 'active',
        ];
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    private function flashErrors(array $errors, array $input): void
    {
        Session::flash('_errors', json_encode($errors, JSON_UNESCAPED_UNICODE) ?: '{}');
        Session::flash('_old', json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /** @return array<string, string> */
    private function popErrors(): array
    {
        $bag = Session::flash('_errors');
        if ($bag === null) return [];
        $arr = json_decode($bag, true);
        return is_array($arr) ? $arr : [];
    }
}
