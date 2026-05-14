<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Request;
use App\Repositories\DealerRepository;
use Throwable;

/**
 * DealerService - business rules for dealer master data.
 *
 * Master data is never hard-deleted; deactivate via setStatus().
 */
final class DealerService
{
    public const STATUSES = ['active', 'inactive'];

    private DealerRepository $dealers;
    private AuditService $audit;

    public function __construct(?DealerRepository $dealers = null, ?AuditService $audit = null)
    {
        $this->dealers = $dealers ?? new DealerRepository();
        $this->audit   = $audit   ?? new AuditService();
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
            $errors['name'] = 'Nama dealer wajib diisi.';
        } elseif (mb_strlen($name) > 191) {
            $errors['name'] = 'Nama dealer maksimal 191 karakter.';
        }

        $code = trim((string) ($input['code'] ?? ''));
        if ($code !== '') {
            if (mb_strlen($code) > 64) {
                $errors['code'] = 'Kode dealer maksimal 64 karakter.';
            } else {
                $existing = $this->dealers->findByCode($code);
                if ($existing !== null && (int) $existing['id'] !== (int) ($exceptId ?? 0)) {
                    $errors['code'] = 'Kode dealer sudah dipakai.';
                }
            }
        }

        $status = (string) ($input['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Status tidak valid.';
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

        $now = date('Y-m-d H:i:s');
        $row = [
            'code'       => $this->orNull((string) ($input['code'] ?? '')),
            'name'       => trim((string) $input['name']),
            'area'       => $this->orNull((string) ($input['area'] ?? '')),
            'address'    => $this->orNull((string) ($input['address'] ?? '')),
            'pic_name'   => $this->orNull((string) ($input['pic_name'] ?? '')),
            'pic_phone'  => $this->orNull((string) ($input['pic_phone'] ?? '')),
            'status'     => (string) ($input['status'] ?? 'active'),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $userId = (int) (Auth::id() ?? 0);
        try {
            $id = $this->dealers->insert($row);
        } catch (Throwable $e) {
            error_log('Dealer insert failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_global' => 'Gagal menyimpan dealer.']];
        }

        $this->audit->log($userId, 'dealer.create', $request, 'dealer', (string) $id, null, [
            'code' => $row['code'], 'name' => $row['name'], 'status' => $row['status'],
        ]);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function update(int $id, array $input, Request $request): array
    {
        $existing = $this->dealers->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'errors' => ['_global' => 'Dealer tidak ditemukan.']];
        }
        $errors = $this->validate($input, $id);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $row = [
            'code'       => $this->orNull((string) ($input['code'] ?? '')),
            'name'       => trim((string) $input['name']),
            'area'       => $this->orNull((string) ($input['area'] ?? '')),
            'address'    => $this->orNull((string) ($input['address'] ?? '')),
            'pic_name'   => $this->orNull((string) ($input['pic_name'] ?? '')),
            'pic_phone'  => $this->orNull((string) ($input['pic_phone'] ?? '')),
            'status'     => (string) ($input['status'] ?? 'active'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $userId = (int) (Auth::id() ?? 0);
        try {
            $this->dealers->update($id, $row);
        } catch (Throwable $e) {
            error_log('Dealer update failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_global' => 'Gagal menyimpan perubahan.']];
        }

        $this->audit->log($userId, 'dealer.update', $request, 'dealer', (string) $id,
            $this->snapshot($existing), $this->snapshot(array_merge($existing, $row))
        );
        return ['ok' => true];
    }

    /**
     * Toggle (or set) status. Soft change only - no row is deleted.
     *
     * @return array{ok: bool, status?: string, error?: string}
     */
    public function setStatus(int $id, string $status, Request $request): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            return ['ok' => false, 'error' => 'Status tidak valid.'];
        }
        $existing = $this->dealers->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Dealer tidak ditemukan.'];
        }
        if ((string) $existing['status'] === $status) {
            return ['ok' => true, 'status' => $status]; // no-op
        }

        $userId = (int) (Auth::id() ?? 0);
        try {
            $this->dealers->update($id, [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log('Dealer status change failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Gagal mengubah status.'];
        }

        $this->audit->log($userId, 'dealer.status', $request, 'dealer', (string) $id,
            ['status' => $existing['status']], ['status' => $status]
        );
        return ['ok' => true, 'status' => $status];
    }

    private function orNull(string $s): ?string
    {
        $s = trim($s);
        return $s === '' ? null : $s;
    }

    /** @param array<string, mixed> $row */
    private function snapshot(array $row): array
    {
        $keep = ['code', 'name', 'area', 'address', 'pic_name', 'pic_phone', 'status'];
        return array_intersect_key($row, array_flip($keep));
    }
}
