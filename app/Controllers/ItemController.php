<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\ItemRepository;
use App\Services\ItemService;

/**
 * ItemController - Phase 5.
 *
 * Read access for any logged-in user. Mutating actions are gated by
 * RoleMiddleware::only(['admin']) at the route layer.
 */
final class ItemController
{
    private ItemService $service;
    private ItemRepository $items;

    public function __construct(?ItemService $service = null, ?ItemRepository $items = null)
    {
        $this->service = $service ?? new ItemService();
        $this->items = $items ?? new ItemRepository();
    }

    public function index(Request $request): string
    {
        $filters = [
            'status' => $request->query('status'),
            'q'      => $request->query('q'),
        ];
        $rows = $this->items->listAll($filters);
        return Response::html(View::render('items/index', [
            'title'     => 'Items - ITPortal',
            'rows'      => $rows,
            'filters'   => $filters,
            'statuses'  => ItemService::STATUSES,
            'canMutate' => Auth::hasAnyRole(['admin']),
        ]));
    }

    public function create(Request $request): string
    {
        return Response::html(View::render('items/form', [
            'title'    => 'Buat Item - ITPortal',
            'mode'     => 'create',
            'item'     => $this->defaultItem(),
            'statuses' => ItemService::STATUSES,
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
            return Response::redirect('/items/create');
        }
        Session::flash('success', 'Item berhasil dibuat.');
        return Response::redirect('/items');
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->items->findById($id);
        if ($item === null) {
            return Response::errorPage(404, '404 Not Found', 'Item tidak ditemukan.');
        }
        return Response::html(View::render('items/form', [
            'title'    => 'Edit Item - ITPortal',
            'mode'     => 'edit',
            'item'     => $item,
            'statuses' => ItemService::STATUSES,
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
            return Response::redirect('/items/' . $id . '/edit');
        }
        Session::flash('success', 'Item berhasil diperbarui.');
        return Response::redirect('/items');
    }

    /** @param array<string,string> $params */
    public function toggleStatus(Request $request, array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $current = $this->items->findById($id);
        if ($current === null) {
            return Response::errorPage(404, '404 Not Found', 'Item tidak ditemukan.');
        }
        $requested = (string) $request->post('status', '');
        if ($requested === '') {
            $requested = ((string) $current['status']) === 'active' ? 'inactive' : 'active';
        }
        $result = $this->service->setStatus($id, $requested, $request);
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? 'Gagal mengubah status.');
        } else {
            Session::flash('success', 'Status item diubah ke ' . ($result['status'] ?? '?') . '.');
        }
        return Response::redirect('/items');
    }

    // ---------- helpers ----------

    /** @return array<string, mixed> */
    private function collect(Request $request): array
    {
        return [
            'name'        => $request->post('name', ''),
            'slug'        => $request->post('slug', ''),
            'description' => $request->post('description', ''),
            'status'      => $request->post('status', 'active'),
            'sort_order'  => $request->post('sort_order', ''),
        ];
    }

    /** @return array<string, mixed> */
    private function defaultItem(): array
    {
        return [
            'id' => 0,
            'name' => '',
            'slug' => '',
            'description' => '',
            'status' => 'active',
            'sort_order' => '',
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
