<?php
/**
 * @var array<int, array<string, mixed>> $rows
 * @var array{total:int, by_status: array<string,int>, avg_lead_time_seconds: ?int} $summary
 * @var array<string, mixed> $filters
 * @var int $year
 * @var int $month
 * @var array<int, array<string, mixed>> $dealers
 * @var array<int, array<string, mixed>> $items
 * @var array<int, string> $statuses
 * @var int $maxRows
 */
$badge = static fn(string $s) => 'badge badge-' . str_replace('_', '-', $s);
$monthName = (function (int $m): string {
    $names = ['', 'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    return $names[$m] ?? (string) $m;
})($month);

// Build a query string keeping the current filters (used by export buttons).
$qs = http_build_query(array_filter([
    'month'     => $month,
    'year'      => $year,
    'status'    => $filters['status'] ?? '',
    'dealer_id' => $filters['dealer_id'] ?? '',
    'item_id'   => $filters['item_id'] ?? '',
    'q'         => $filters['q'] ?? '',
], static fn($v) => $v !== '' && $v !== null));
?>

<section class="page-header">
    <h1>Laporan Bulanan</h1>
    <div class="page-header-actions">
        <a class="btn" href="/exports/monthly/excel?<?= e($qs) ?>"
           title="Download laporan dalam format Excel (.xlsx)">
            Export Excel
        </a>
        <a class="btn" href="/exports/monthly/pdf?<?= e($qs) ?>"
           title="Download laporan dalam format PDF (landscape)">
            Export PDF
        </a>
    </div>
</section>

<form method="get" action="/reports/monthly" class="card filter-bar" aria-label="Filter laporan bulanan">
    <div class="filter-grid">
        <div class="field">
            <label>Bulan</label>
            <select name="month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                        <?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="field">
            <label>Tahun</label>
            <input type="number" name="year" min="2000" max="2099" value="<?= (int) $year ?>">
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="">- Semua -</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>>
                        <?= e(str_replace('_', ' ', $s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Dealer</label>
            <select name="dealer_id">
                <option value="">- Semua -</option>
                <?php foreach ($dealers as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"
                            <?= ((int) ($filters['dealer_id'] ?? 0) === (int) $d['id']) ? 'selected' : '' ?>>
                        <?= e((string) $d['name']) ?>
                        <?php if ((string) $d['status'] === 'inactive'): ?>(inactive)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Item</label>
            <select name="item_id">
                <option value="">- Semua -</option>
                <?php foreach ($items as $it): ?>
                    <option value="<?= (int) $it['id'] ?>"
                            <?= ((int) ($filters['item_id'] ?? 0) === (int) $it['id']) ? 'selected' : '' ?>>
                        <?= e((string) $it['name']) ?>
                        <?php if ((string) $it['status'] === 'inactive'): ?>(inactive)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field-grow">
            <label>Cari</label>
            <input type="search" name="q" placeholder="Nomor / pelapor / dealer / isi"
                   value="<?= e((string) ($filters['q'] ?? '')) ?>">
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a href="/reports/monthly" class="btn">Reset</a>
        <span class="muted">
            Periode: <?= e($monthName) ?> <?= (int) $year ?>
            &middot; <?= count($rows) ?> baris ditampilkan
            (max <?= (int) $maxRows ?>)
        </span>
    </div>
</form>

<section class="card">
    <h2>Ringkasan Bulanan</h2>
    <ul class="summary-inline">
        <li><strong>Total:</strong> <?= (int) $summary['total'] ?></li>
        <?php foreach ($statuses as $s):
            $c = (int) ($summary['by_status'][$s] ?? 0);
        ?>
            <li>
                <span class="<?= $badge($s) ?>"><?= e(str_replace('_', ' ', $s)) ?></span>
                <strong><?= $c ?></strong>
            </li>
        <?php endforeach; ?>
        <li>
            <strong>Avg lead time (closed):</strong>
            <?= e(format_lead_time($summary['avg_lead_time_seconds'])) ?: '-' ?>
        </li>
    </ul>
</section>

<?php if ($rows === []): ?>
    <p class="muted">Tidak ada ticket pada periode/filter ini.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Tgl</th>
                    <th>Dealer</th>
                    <th>Item</th>
                    <th>Pelapor</th>
                    <th>Status</th>
                    <th>Lead Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><a href="/tickets/<?= (int) $r['id'] ?>"><?= e((string) $r['ticket_number']) ?></a></td>
                        <td><?= e(format_date_id((string) $r['report_date'])) ?></td>
                        <td><?= e((string) $r['dealer_name']) ?></td>
                        <td><?= e((string) $r['item_name']) ?></td>
                        <td><?= e((string) $r['reporter_name']) ?></td>
                        <td><span class="<?= $badge((string) $r['status']) ?>"><?= e(str_replace('_', ' ', (string) $r['status'])) ?></span></td>
                        <td><?= e(format_lead_time(isset($r['lead_time_seconds']) ? (int) $r['lead_time_seconds'] : null)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="cards-mobile">
        <?php foreach ($rows as $r): ?>
            <a href="/tickets/<?= (int) $r['id'] ?>" class="card ticket-card">
                <div class="ticket-card-head">
                    <strong><?= e((string) $r['ticket_number']) ?></strong>
                    <span class="<?= $badge((string) $r['status']) ?>"><?= e(str_replace('_', ' ', (string) $r['status'])) ?></span>
                </div>
                <div class="muted">
                    <?= e(format_date_id((string) $r['report_date'])) ?> &middot;
                    <?= e((string) $r['item_name']) ?>
                </div>
                <div><?= e((string) $r['dealer_name']) ?></div>
                <div class="muted">Pelapor: <?= e((string) $r['reporter_name']) ?></div>
                <?php if (!empty($r['lead_time_seconds'])): ?>
                    <div class="muted">Lead time: <?= e(format_lead_time((int) $r['lead_time_seconds'])) ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php unset($qs); ?>
