<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Request;
use App\Repositories\ItemRepository;
use Throwable;

/**
 * ItemService - business rules for item/category master data.
 *
 * Slug is auto-generated from name when blank, lowercase, ascii-friendly,
 * and guaranteed unique. Master data is never hard-deleted.
 */
final class ItemService
{
    public const STATUSES = ['active', 'inactive'];

    private ItemRepository $items;
    private AuditService $audit;

    public function __construct(?ItemRepository $items = null, ?AuditService $audit = null)
    {
        $this->items = $items ?? new ItemRepository();
        $this->audit = $audit ?? new AuditService();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function validate(array $input, ?int $exceptId = null): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Nama item wajib diisi.';
        } elseif (mb_strlen($name) > 191) {
            $errors['name'] = 'Nama item maksimal 191 karakter.';
        }

        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug !== '') {
            if (!preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $slug)) {
                $errors['slug'] = 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.';
            } elseif (mb_strlen($slug) > 191) {
                $errors['slug'] = 'Slug maksimal 191 karakter.';
            } else {
                $existing = $this->items->findBySlug($slug);
                if ($existing !== null && (int) $existing['id'] !== (int) ($exceptId ?? 0)) {
                    $errors['slug'] = 'Slug sudah dipakai item lain.';
                }
            }
        }

        $status = (string) ($input['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Status tidak valid.';
        }

        $sortOrder = $input['sort_order'] ?? '';
        if ($sortOrder !== '' && !ctype_digit((string) $sortOrder)) {
            $errors['sort_order'] = 'Urutan harus angka >= 0.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, id?: int, errors?: array<string, string>}
     */
    public function create(array $input, Request $request): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $name = trim((string) $input['name']);
        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->makeUniqueSlug($this->slugify($name));
        }

        $sortOrderRaw = (string) ($input['sort_order'] ?? '');
        $sortOrder = $sortOrderRaw === '' ? ($this->items->maxSortOrder() + 10) : (int) $sortOrderRaw;

        $now = date('Y-m-d H:i:s');
        $row = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $this->orNull((string) ($input['description'] ?? '')),
            'status'      => (string) ($input['status'] ?? 'active'),
            'sort_order'  => $sortOrder,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        $userId = (int) (Auth::id() ?? 0);
        try {
            $id = $this->items->insert($row);
        } catch (Throwable $e) {
            error_log('Item insert failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_global' => 'Gagal menyimpan item.']];
        }

        $this->audit->log($userId, 'item.create', $request, 'item', (string) $id, null, [
            'name' => $row['name'], 'slug' => $row['slug'], 'status' => $row['status'],
        ]);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function update(int $id, array $input, Request $request): array
    {
        $existing = $this->items->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'errors' => ['_global' => 'Item tidak ditemukan.']];
        }
        $errors = $this->validate($input, $id);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $name = trim((string) $input['name']);
        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            // keep existing slug; do NOT silently rewrite when blanked.
            $slug = (string) $existing['slug'];
        }

        $sortOrderRaw = (string) ($input['sort_order'] ?? '');
        $sortOrder = $sortOrderRaw === '' ? (int) $existing['sort_order'] : (int) $sortOrderRaw;

        $row = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $this->orNull((string) ($input['description'] ?? '')),
            'status'      => (string) ($input['status'] ?? 'active'),
            'sort_order'  => $sortOrder,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $userId = (int) (Auth::id() ?? 0);
        try {
            $this->items->update($id, $row);
        } catch (Throwable $e) {
            error_log('Item update failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_global' => 'Gagal menyimpan perubahan.']];
        }

        $this->audit->log($userId, 'item.update', $request, 'item', (string) $id,
            $this->snapshot($existing), $this->snapshot(array_merge($existing, $row))
        );
        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, status?: string, error?: string}
     */
    public function setStatus(int $id, string $status, Request $request): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            return ['ok' => false, 'error' => 'Status tidak valid.'];
        }
        $existing = $this->items->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Item tidak ditemukan.'];
        }
        if ((string) $existing['status'] === $status) {
            return ['ok' => true, 'status' => $status];
        }

        $userId = (int) (Auth::id() ?? 0);
        try {
            $this->items->update($id, [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log('Item status change failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Gagal mengubah status.'];
        }

        $this->audit->log($userId, 'item.status', $request, 'item', (string) $id,
            ['status' => $existing['status']], ['status' => $status]
        );
        return ['ok' => true, 'status' => $status];
    }

    // ---------- helpers ----------

    public function slugify(string $name): string
    {
        $s = strtolower(trim($name));
        // Replace any non-alphanumeric with hyphen, collapse repeats, trim hyphens.
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if ($s === '') {
            $s = 'item-' . substr((string) time(), -6);
        }
        return $s;
    }

    private function makeUniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while ($this->items->findBySlug($slug) !== null) {
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 1000) {
                $slug = $base . '-' . substr((string) time(), -6);
                break;
            }
        }
        return $slug;
    }

    private function orNull(string $s): ?string
    {
        $s = trim($s);
        return $s === '' ? null : $s;
    }

    /** @param array<string, mixed> $row */
    private function snapshot(array $row): array
    {
        $keep = ['name', 'slug', 'description', 'status', 'sort_order'];
        return array_intersect_key($row, array_flip($keep));
    }
}
