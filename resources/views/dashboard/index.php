<?php
/**
 * @var array<string, mixed> $user
 * @var int $year
 * @var int $month
 * @var array{total:int, by_status: array<string,int>, avg_lead_time_seconds: ?int} $summary
 * @var array<int, array{dealer_id:int, dealer_name:string, total:int}> $topDealers
 * @var array<int, array{item_id:int, item_name:string, total:int}> $topItems
 * @var array<int, array<string, mixed>> $recent
 * @var array<int, string> $statuses
 */
$badge = static fn(string $s) => 'badge badge-' . str_replace('_', '-', $s);
$monthName = (function (int $m): string {
    $names = ['', 'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    return $names[$m] ?? (string) $m;
})($month);

$statusLabel = static function (string $s): string {
    return [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'pending' => 'Pending',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ][$s] ?? $s;
};
?>

<section class="page-header">
    <h1>Dashboard</h1>
    <div class="page-header-actions">
        <span class="muted">Halo, <strong><?= e((string) ($user['name'] ?? '')) ?></strong>
            (<code><?= e((string) ($user['role'] ?? '')) ?></code>)</span>
    </div>
</section>

<form method="get" action="/dashboard" class="card filter-bar" aria-label="Filter periode dashboard">
    <div class="filter-grid">
        <div class="field">
            <label for="dash-month">Bulan</label>
            <select id="dash-month" name="month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                        <?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="field">
            <label for="dash-year">Tahun</label>
            <input id="dash-year" type="number" name="year" min="2000" max="2099" value="<?= (int) $year ?>">
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a href="/dashboard" class="btn">Bulan ini</a>
        <span class="muted">Periode: <?= e($monthName) ?> <?= (int) $year ?></span>
    </div>
</form>

<section class="stat-grid" aria-label="Ringkasan ticket bulan terpilih">
    <article class="stat-card">
        <div class="stat-label">Total Ticket</div>
        <div class="stat-value"><?= (int) $summary['total'] ?></div>
        <div class="muted">Bulan <?= e($monthName) ?> <?= (int) $year ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Avg Lead Time (closed)</div>
        <div class="stat-value">
            <?= e(format_lead_time($summary['avg_lead_time_seconds'])) ?: '-' ?>
        </div>
        <div class="muted">
            <?php $closed = (int) ($summary['by_status']['closed'] ?? 0); ?>
            Berdasarkan <?= $closed ?> ticket closed
        </div>
    </article>
    <?php foreach ($statuses as $s):
        $count = (int) ($summary['by_status'][$s] ?? 0);
    ?>
        <article class="stat-card stat-card-status">
            <div class="stat-label"><span class="<?= $badge($s) ?>"><?= e($statusLabel($s)) ?></span></div>
            <div class="stat-value"><?= $count ?></div>
            <a class="muted" href="/tickets?month=<?= (int) $month ?>&year=<?= (int) $year ?>&status=<?= e($s) ?>">
                Lihat ticket <?= e($statusLabel($s)) ?> &rsaquo;
            </a>
        </article>
    <?php endforeach; ?>
</section>

<section class="grid-2 dash-tops">
    <article class="card">
        <h2>Top Dealer</h2>
        <?php if ($topDealers === []): ?>
            <p class="muted">Tidak ada data ticket pada periode ini.</p>
        <?php else: ?>
            <ol class="rank-list">
                <?php foreach ($topDealers as $d): ?>
                    <li>
                        <a href="/tickets?month=<?= (int) $month ?>&year=<?= (int) $year ?>&dealer_id=<?= (int) $d['dealer_id'] ?>">
                            <?= e($d['dealer_name']) ?>
                        </a>
                        <span class="rank-count"><?= (int) $d['total'] ?> ticket</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </article>
    <article class="card">
        <h2>Top Item</h2>
        <?php if ($topItems === []): ?>
            <p class="muted">Tidak ada data ticket pada periode ini.</p>
        <?php else: ?>
            <ol class="rank-list">
                <?php foreach ($topItems as $it): ?>
                    <li>
                        <a href="/tickets?month=<?= (int) $month ?>&year=<?= (int) $year ?>&item_id=<?= (int) $it['item_id'] ?>">
                            <?= e($it['item_name']) ?>
                        </a>
                        <span class="rank-count"><?= (int) $it['total'] ?> ticket</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </article>
</section>

<section class="card">
    <div class="page-header">
        <h2>Ticket Terbaru Bulan Ini</h2>
        <a href="/tickets?month=<?= (int) $month ?>&year=<?= (int) $year ?>" class="btn">Lihat semua &rsaquo;</a>
    </div>
    <?php if ($recent === []): ?>
        <p class="muted">Belum ada ticket pada periode ini.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tgl</th>
                        <th>Dealer</th>
                        <th>Item</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><a href="/tickets/<?= (int) $r['id'] ?>"><?= e((string) $r['ticket_number']) ?></a></td>
                            <td><?= e(format_date_id((string) $r['report_date'])) ?></td>
                            <td><?= e((string) $r['dealer_name']) ?></td>
                            <td><?= e((string) $r['item_name']) ?></td>
                            <td><span class="<?= $badge((string) $r['status']) ?>"><?= e($statusLabel((string) $r['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cards-mobile">
            <?php foreach ($recent as $r): ?>
                <a href="/tickets/<?= (int) $r['id'] ?>" class="card ticket-card">
                    <div class="ticket-card-head">
                        <strong><?= e((string) $r['ticket_number']) ?></strong>
                        <span class="<?= $badge((string) $r['status']) ?>"><?= e($statusLabel((string) $r['status'])) ?></span>
                    </div>
                    <div class="muted"><?= e(format_date_id((string) $r['report_date'])) ?></div>
                    <div><?= e((string) $r['dealer_name']) ?> &middot; <?= e((string) $r['item_name']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

