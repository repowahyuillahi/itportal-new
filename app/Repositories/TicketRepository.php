<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * TicketRepository - all SQL touching `tickets` lives here.
 *
 * All filter inputs are bound via PDO parameters; never concatenated into
 * the SQL string.
 */
final class TicketRepository
{
    private function pdo(): PDO
    {
        return Database::pdo();
    }

    private const SELECT_BASE =
        't.id, t.ticket_number, t.status, t.report_date, t.reporter_name,
         t.dealer_id, t.item_id, t.initial_report, t.checking_notes, t.solution,
         t.started_at, t.finished_at, t.lead_time_seconds,
         t.assigned_user_id, t.created_by, t.updated_by, t.closed_by, t.closed_at,
         t.created_at, t.updated_at,
         d.name AS dealer_name, d.code AS dealer_code,
         i.name AS item_name, i.slug AS item_slug,
         u_assigned.name AS assigned_user_name,
         u_creator.name  AS creator_name';

    private const FROM_BASE =
        'FROM tickets t
         INNER JOIN dealers d ON d.id = t.dealer_id
         INNER JOIN items   i ON i.id = t.item_id
         LEFT  JOIN users u_assigned ON u_assigned.id = t.assigned_user_id
         LEFT  JOIN users u_creator  ON u_creator.id  = t.created_by';

    /**
     * Build a parameterised WHERE clause from filters.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        $month = isset($filters['month']) ? (int) $filters['month'] : 0;
        $year  = isset($filters['year'])  ? (int) $filters['year']  : 0;
        if ($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 9999) {
            $clauses[] = 'YEAR(t.report_date) = :year AND MONTH(t.report_date) = :month';
            $params['year'] = $year;
            $params['month'] = $month;
        } elseif ($year >= 2000 && $year <= 9999) {
            $clauses[] = 'YEAR(t.report_date) = :year';
            $params['year'] = $year;
        }

        if (!empty($filters['status'])) {
            $clauses[] = 't.status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['dealer_id'])) {
            $clauses[] = 't.dealer_id = :dealer_id';
            $params['dealer_id'] = (int) $filters['dealer_id'];
        }
        if (!empty($filters['item_id'])) {
            $clauses[] = 't.item_id = :item_id';
            $params['item_id'] = (int) $filters['item_id'];
        }

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $clauses[] = '(t.ticket_number LIKE :q1
                          OR t.reporter_name LIKE :q2
                          OR t.initial_report LIKE :q3
                          OR d.name LIKE :q4)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        [$where, $params] = $this->buildWhere($filters);

        $countSql = 'SELECT COUNT(*) ' . self::FROM_BASE . ' ' . $where;
        $countStmt = $this->pdo()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT ' . self::SELECT_BASE . ' ' . self::FROM_BASE . ' ' . $where
             . ' ORDER BY t.report_date DESC, t.id DESC'
             . ' LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT ' . self::SELECT_BASE . ' ' . self::FROM_BASE . ' WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Returns the next available ticket number, formatted `TKT-YYYYMM-####`. */
    public function nextTicketNumber(\DateTimeImmutable $reportDate): string
    {
        $prefix = 'TKT-' . $reportDate->format('Ym') . '-';
        $stmt = $this->pdo()->prepare(
            'SELECT ticket_number FROM tickets
             WHERE ticket_number LIKE :prefix
             ORDER BY ticket_number DESC
             LIMIT 1'
        );
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();
        $next = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $sql =
            'INSERT INTO tickets
             (ticket_number, status, report_date, reporter_name,
              dealer_id, item_id, initial_report, checking_notes, solution,
              started_at, finished_at, lead_time_seconds,
              assigned_user_id, created_by, updated_by,
              created_at, updated_at)
             VALUES
             (:ticket_number, :status, :report_date, :reporter_name,
              :dealer_id, :item_id, :initial_report, :checking_notes, :solution,
              :started_at, :finished_at, :lead_time_seconds,
              :assigned_user_id, :created_by, :updated_by,
              :created_at, :updated_at)';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($data);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Aggregate counts and lead-time average for a given month.
     *
     * Returns:
     *   total: int
     *   by_status: ['open'=>int, 'in_progress'=>int, 'pending'=>int, 'closed'=>int, 'cancelled'=>int]
     *   avg_lead_time_seconds: int|null  (only over closed tickets with lead_time_seconds)
     *
     * @return array{total: int, by_status: array<string, int>, avg_lead_time_seconds: int|null}
     */
    public function summaryForMonth(int $year, int $month): array
    {
        $statuses = ['open', 'in_progress', 'pending', 'closed', 'cancelled'];
        $byStatus = array_fill_keys($statuses, 0);

        $sql = 'SELECT t.status, COUNT(*) AS c
                FROM tickets t
                WHERE YEAR(t.report_date) = :year AND MONTH(t.report_date) = :month
                GROUP BY t.status';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['year' => $year, 'month' => $month]);
        $total = 0;
        foreach ($stmt->fetchAll() as $row) {
            $key = (string) $row['status'];
            $count = (int) $row['c'];
            if (array_key_exists($key, $byStatus)) {
                $byStatus[$key] = $count;
            }
            $total += $count;
        }

        $avgSql = 'SELECT AVG(lead_time_seconds) AS a
                   FROM tickets
                   WHERE YEAR(report_date) = :year AND MONTH(report_date) = :month
                     AND status = "closed"
                     AND lead_time_seconds IS NOT NULL';
        $stmt = $this->pdo()->prepare($avgSql);
        $stmt->execute(['year' => $year, 'month' => $month]);
        $avg = $stmt->fetchColumn();
        $avgLead = ($avg === null || $avg === false) ? null : (int) round((float) $avg);

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'avg_lead_time_seconds' => $avgLead,
        ];
    }

    /**
     * Top dealers by ticket count for a given month.
     *
     * @return array<int, array{dealer_id: int, dealer_name: string, total: int}>
     */
    public function topDealersForMonth(int $year, int $month, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $sql = 'SELECT t.dealer_id, d.name AS dealer_name, COUNT(*) AS total
                FROM tickets t
                INNER JOIN dealers d ON d.id = t.dealer_id
                WHERE YEAR(t.report_date) = :year AND MONTH(t.report_date) = :month
                GROUP BY t.dealer_id, d.name
                ORDER BY total DESC, d.name ASC
                LIMIT :lim';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':month', $month, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static fn(array $r) => [
            'dealer_id' => (int) $r['dealer_id'],
            'dealer_name' => (string) $r['dealer_name'],
            'total' => (int) $r['total'],
        ], $stmt->fetchAll());
    }

    /**
     * Top items by ticket count for a given month.
     *
     * @return array<int, array{item_id: int, item_name: string, total: int}>
     */
    public function topItemsForMonth(int $year, int $month, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $sql = 'SELECT t.item_id, i.name AS item_name, COUNT(*) AS total
                FROM tickets t
                INNER JOIN items i ON i.id = t.item_id
                WHERE YEAR(t.report_date) = :year AND MONTH(t.report_date) = :month
                GROUP BY t.item_id, i.name
                ORDER BY total DESC, i.name ASC
                LIMIT :lim';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':month', $month, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static fn(array $r) => [
            'item_id' => (int) $r['item_id'],
            'item_name' => (string) $r['item_name'],
            'total' => (int) $r['total'],
        ], $stmt->fetchAll());
    }

    /**
     * Recent tickets for a given month (DESC by id).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentTicketsForMonth(int $year, int $month, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $sql = 'SELECT ' . self::SELECT_BASE . ' ' . self::FROM_BASE . '
                WHERE YEAR(t.report_date) = :year AND MONTH(t.report_date) = :month
                ORDER BY t.id DESC
                LIMIT :lim';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':month', $month, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count rows that match the report filter (no LIMIT). Used by export
     * controller to enforce the row cap before materializing the full set.
     *
     * @param array<string, mixed> $filters
     */
    public function countForReport(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT COUNT(*) ' . self::FROM_BASE . ' ' . $where;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Flat list for the report preview / export - reuses the paginate()
     * filter shape, no pagination, capped at $limit rows.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listForReport(array $filters, int $limit = 500): array
    {
        $limit = max(1, min(5000, $limit));
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT ' . self::SELECT_BASE . ' ' . self::FROM_BASE . ' ' . $where
             . ' ORDER BY t.report_date DESC, t.id DESC LIMIT :lim';
        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        // Build SET clause from provided keys.
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "$col = :$col";
        }
        $data['id'] = $id;
        $sql = 'UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($data);
    }
}
