<?php
/**
 * @var array<int, array<string, mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $perPage
 * @var array<string, mixed> $filters
 * @var array<int, array<string, mixed>> $dealers
 * @var array<int, array<string, mixed>> $items
 * @var array<int, string> $statuses
 * @var bool $canMutate
 */

$qs = static function (array $extra) use ($filters): string {
    $merged = array_filter(array_merge([
        'month' => $filters['month'] ?? '',
        'year' => $filters['year'] ?? '',
        'status' => $filters['status'] ?? '',
        'dealer_id' => $filters['dealer_id'] ?? '',
        'item_id' => $filters['item_id'] ?? '',
        'q' => $filters['q'] ?? '',
    ], $extra), static fn($v) => $v !== '' && $v !== null);
    return $merged === [] ? '' : '?' . http_build_query($merged);
};
$badge = static fn(string $s) => 'badge badge-' . str_replace('_', '-', $s);
?>

<section class="page-header">
    <h1>Tickets</h1>
    <?php if ($canMutate): ?>
        <a href="/tickets/create" class="btn btn-primary">+ Buat Ticket</a>
    <?php endif; ?>
</section>

<form method="get" action="/tickets" class="card filter-bar">
    <div class="filter-grid">
        <div class="field">
            <label>Bulan</label>
            <select name="month">
                <option value="">- Semua -</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ((int) ($filters['month'] ?? 0) === $m) ? 'selected' : '' ?>>
                        <?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="field">
            <label>Tahun</label>
            <input type="number" name="year" min="2000" max="2099"
                   value="<?= e((string) ($filters['year'] ?? date('Y'))) ?>">
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
        <a href="/tickets" class="btn">Reset</a>
        <span class="muted">Total <?= (int) $total ?> ticket</span>
    </div>
</form>

<?php if ($rows === []): ?>
    <p class="muted">Belum ada ticket sesuai filter.</p>
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
                    <th></th>
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
                        <td><a href="/tickets/<?= (int) $r['id'] ?>" class="btn btn-link">Detail</a></td>
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
                <div class="muted"><?= e(format_date_id((string) $r['report_date'])) ?> &middot; <?= e((string) $r['item_name']) ?></div>
                <div><?= e((string) $r['dealer_name']) ?></div>
                <div class="muted">Pelapor: <?= e((string) $r['reporter_name']) ?></div>
                <?php if (!empty($r['lead_time_seconds'])): ?>
                    <div class="muted">Lead time: <?= e(format_lead_time((int) $r['lead_time_seconds'])) ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn" href="/tickets<?= e($qs(['page' => $page - 1])) ?>">&laquo; Prev</a>
            <?php endif; ?>
            <span class="muted">Halaman <?= (int) $page ?> dari <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn" href="/tickets<?= e($qs(['page' => $page + 1])) ?>">Next &raquo;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
