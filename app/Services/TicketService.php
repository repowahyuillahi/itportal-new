<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Request;
use App\Repositories\DealerRepository;
use App\Repositories\ItemRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use Throwable;

/**
 * TicketService - business rules for tickets.
 *
 * Validation, number generation, lead time, and audit logging all happen
 * here so controllers stay thin.
 */
final class TicketService
{
    public const STATUSES = ['open', 'in_progress', 'pending', 'closed', 'cancelled'];

    private TicketRepository $tickets;
    private DealerRepository $dealers;
    private ItemRepository $items;
    private UserRepository $users;
    private AuditService $audit;

    public function __construct(
        ?TicketRepository $tickets = null,
        ?DealerRepository $dealers = null,
        ?ItemRepository   $items   = null,
        ?UserRepository   $users   = null,
        ?AuditService     $audit   = null,
    ) {
        $this->tickets = $tickets ?? new TicketRepository();
        $this->dealers = $dealers ?? new DealerRepository();
        $this->items   = $items   ?? new ItemRepository();
        $this->users   = $users   ?? new UserRepository();
        $this->audit   = $audit   ?? new AuditService();
    }

    /**
     * Validate incoming form input.
     *
     * @param array<string, mixed> $input
     * @return array<string, string> errors keyed by field name (empty = ok)
     */
    public function validate(array $input, bool $isUpdate = false): array
    {
        $errors = [];

        $reporterName = trim((string) ($input['reporter_name'] ?? ''));
        if ($reporterName === '') {
            $errors['reporter_name'] = 'Nama pelapor wajib diisi.';
        } elseif (mb_strlen($reporterName) > 150) {
            $errors['reporter_name'] = 'Nama pelapor maksimal 150 karakter.';
        }

        $reportDate = (string) ($input['report_date'] ?? '');
        if ($reportDate === '' || !$this->isValidDate($reportDate)) {
            $errors['report_date'] = 'Tanggal laporan tidak valid (format YYYY-MM-DD).';
        }

        $dealerId = (int) ($input['dealer_id'] ?? 0);
        if ($dealerId <= 0 || !$this->dealers->exists($dealerId)) {
            $errors['dealer_id'] = 'Dealer tidak valid.';
        }

        $itemId = (int) ($input['item_id'] ?? 0);
        if ($itemId <= 0 || !$this->items->exists($itemId)) {
            $errors['item_id'] = 'Item tidak valid.';
        }

        $initial = trim((string) ($input['initial_report'] ?? ''));
        if ($initial === '') {
            $errors['initial_report'] = 'Deskripsi laporan awal wajib diisi.';
        }

        $startedAt = (string) ($input['started_at'] ?? '');
        if ($startedAt === '' || !$this->isValidDateTime($startedAt)) {
            $errors['started_at'] = 'Waktu mulai tidak valid (format YYYY-MM-DD HH:MM).';
        }

        $status = (string) ($input['status'] ?? 'open');
        if (!in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Status tidak valid.';
        }

        $finishedAt = trim((string) ($input['finished_at'] ?? ''));
        if ($finishedAt !== '') {
            if (!$this->isValidDateTime($finishedAt)) {
                $errors['finished_at'] = 'Waktu selesai tidak valid.';
            } elseif (!isset($errors['started_at']) && strtotime($finishedAt) < strtotime($startedAt)) {
                $errors['finished_at'] = 'Waktu selesai harus >= waktu mulai.';
            }
        } elseif ($status === 'closed') {
            $errors['finished_at'] = 'Status closed wajib mengisi waktu selesai.';
        }

        $assigned = (string) ($input['assigned_user_id'] ?? '');
        if ($assigned !== '' && $assigned !== '0') {
            $aid = (int) $assigned;
            if ($aid <= 0 || $this->users->findById($aid) === null) {
                $errors['assigned_user_id'] = 'Petugas yang ditugaskan tidak valid.';
            }
        }

        unset($isUpdate); // currently same rules; reserved for future asymmetry
        return $errors;
    }

    /**
     * Create a ticket.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, id?: int, errors?: array<string, string>}
     */
    public function create(array $input, Request $request): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'errors' => ['_global' => 'Sesi tidak valid.']];
        }

        $reportDate = new DateTimeImmutable((string) $input['report_date']);
        $number = $this->tickets->nextTicketNumber($reportDate);

        $startedAt = $this->normalizeDateTime((string) $input['started_at']);
        $finishedAtRaw = trim((string) ($input['finished_at'] ?? ''));
        $finishedAt = $finishedAtRaw === '' ? null : $this->normalizeDateTime($finishedAtRaw);
        $leadTime = $this->computeLeadTime($startedAt, $finishedAt);

        $now = date('Y-m-d H:i:s');
        $assigned = (string) ($input['assigned_user_id'] ?? '');
        $row = [
            'ticket_number'     => $number,
            'status'            => (string) $input['status'],
            'report_date'       => $reportDate->format('Y-m-d'),
            'reporter_name'     => trim((string) $input['reporter_name']),
            'dealer_id'         => (int) $input['dealer_id'],
            'item_id'           => (int) $input['item_id'],
            'initial_report'    => trim((string) $input['initial_report']),
            'checking_notes'    => $this->orNull((string) ($input['checking_notes'] ?? '')),
            'solution'          => $this->orNull((string) ($input['solution'] ?? '')),
            'started_at'        => $startedAt,
            'finished_at'       => $finishedAt,
            'lead_time_seconds' => $leadTime,
            'assigned_user_id'  => $assigned === '' || $assigned === '0' ? null : (int) $assigned,
            'created_by'        => $userId,
            'updated_by'        => null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        try {
            $id = $this->tickets->insert($row);
        } catch (Throwable $e) {
            error_log('Ticket insert failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_global' => 'Gagal menyimpan ticket.']];
        }

        $this->audit->log($userId, 'ticket.create', $request, 'ticket', (string) $id, null, [
            'ticket_number' => $number,
            'status' => $row['status'],
            'dealer_id' => $row['dealer_id'],
            'item_id' => $row['item_id'],
        ]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Update a ticket. Returns errors keyed by field on failure.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function update(int $id, array $input, Request $request): array
    {
        $existing = $this->tickets->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'errors' => ['_global' => 'Ticket tidak ditemukan.']];
        }
        $errors = $this->validate($input, true);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $userId = (int) (Auth::id() ?? 0);

        $startedAt = $this->normalizeDateTime((string) $input['started_at']);
        $finishedAtRaw = trim((string) ($input['finished_at'] ?? ''));
        $finishedAt = $finishedAtRaw === '' ? null : $this->normalizeDateTime($finishedAtRaw);
        $leadTime = $this->computeLeadTime($startedAt, $finishedAt);

        $assigned = (string) ($input['assigned_user_id'] ?? '');
        $row = [
            'status'            => (string) $input['status'],
            'report_date'       => (new DateTimeImmutable((string) $input['report_date']))->format('Y-m-d'),
            'reporter_name'     => trim((string) $input['reporter_name']),
            'dealer_id'         => (int) $input['dealer_id'],
            'item_id'           => (int) $input['item_id'],
            'initial_report'    => trim((string) $input['initial_report']),
            'checking_notes'    => $this->orNull((string) ($input['checking_notes'] ?? '')),
            'solution'          => $this->orNull((string) ($input['solution'] ?? '')),
            'started_at'        => $startedAt,
            'finished_at'       => $finishedAt,
            'lead_time_seconds' => $leadTime,
            'assigned_user_id'  => $assigned === '' || $assigned === '0' ? null : (int) $assigned,
            'updated_by'        => $userId,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        // If transitioning to/away from closed, keep closed_* in sync.
        if ($row['status'] === 'closed' && $existing['status'] !== 'closed') {
            $row['closed_by'] = $userId;
            $row['closed_at'] = $row['finished_at'] ?? date('Y-m-d H:i:s');
        } elseif ($row['status'] !== 'closed' && $existing['status'] === 'closed') {
            $row['closed_by'] = null;
            $row['closed_at'] = null;
        }

        try {
            $this->tickets->update($id, $row);
        } catch (Throwable $e) {
            error_log('Ticket update failed: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['_global' => 'Gagal menyimpan perubahan.']];
        }

        $this->audit->log($userId, 'ticket.update', $request, 'ticket', (string) $id,
            $this->auditSnapshot($existing), $this->auditSnapshot(array_merge($existing, $row))
        );

        return ['ok' => true];
    }

    /**
     * Close a ticket: set status=closed, finished_at=now if missing,
     * compute lead time, set closed_by/closed_at.
     *
     * @return array{ok: bool, error?: string}
     */
    public function close(int $id, Request $request): array
    {
        $existing = $this->tickets->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Ticket tidak ditemukan.'];
        }
        if ($existing['status'] === 'closed') {
            return ['ok' => false, 'error' => 'Ticket sudah closed.'];
        }
        if ($existing['status'] === 'cancelled') {
            return ['ok' => false, 'error' => 'Ticket sudah cancelled, tidak bisa di-close.'];
        }

        $userId = (int) (Auth::id() ?? 0);
        $now = date('Y-m-d H:i:s');
        $finishedAt = $existing['finished_at'] !== null && $existing['finished_at'] !== ''
            ? (string) $existing['finished_at']
            : $now;
        $leadTime = $this->computeLeadTime((string) $existing['started_at'], $finishedAt);

        $row = [
            'status'            => 'closed',
            'finished_at'       => $finishedAt,
            'lead_time_seconds' => $leadTime,
            'closed_by'         => $userId,
            'closed_at'         => $finishedAt,
            'updated_by'        => $userId,
            'updated_at'        => $now,
        ];

        try {
            $this->tickets->update($id, $row);
        } catch (Throwable $e) {
            error_log('Ticket close failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Gagal close ticket.'];
        }

        $this->audit->log($userId, 'ticket.close', $request, 'ticket', (string) $id,
            $this->auditSnapshot($existing), $this->auditSnapshot(array_merge($existing, $row))
        );

        return ['ok' => true];
    }

    // ---------- helpers ----------

    private function isValidDate(string $s): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $s);
        return $d !== false && $d->format('Y-m-d') === $s;
    }

    private function isValidDateTime(string $s): bool
    {
        // Accept "YYYY-MM-DD HH:MM" or "YYYY-MM-DDTHH:MM" (HTML datetime-local).
        $s = str_replace('T', ' ', $s);
        if (strlen($s) === 16) {
            $s .= ':00';
        }
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $s);
        return $d !== false && $d->format('Y-m-d H:i:s') === $s;
    }

    private function normalizeDateTime(string $s): string
    {
        $s = str_replace('T', ' ', trim($s));
        if (strlen($s) === 16) {
            $s .= ':00';
        }
        return $s;
    }

    private function computeLeadTime(string $startedAt, ?string $finishedAt): ?int
    {
        if ($finishedAt === null || $finishedAt === '') {
            return null;
        }
        $a = strtotime($startedAt);
        $b = strtotime($finishedAt);
        if ($a === false || $b === false) {
            return null;
        }
        $diff = $b - $a;
        return $diff < 0 ? 0 : $diff;
    }

    private function orNull(string $s): ?string
    {
        $s = trim($s);
        return $s === '' ? null : $s;
    }

    /** @param array<string, mixed> $row */
    private function auditSnapshot(array $row): array
    {
        $keep = ['status', 'report_date', 'reporter_name', 'dealer_id', 'item_id',
                 'initial_report', 'checking_notes', 'solution',
                 'started_at', 'finished_at', 'lead_time_seconds',
                 'assigned_user_id', 'closed_by', 'closed_at'];
        return array_intersect_key($row, array_flip($keep));
    }
}
