<?php
/**
 * @var array<int, array<string, mixed>> $rows
 * @var array<string, mixed> $filters
 * @var array<int, string> $statuses
 * @var bool $canMutate
 */
$badge = static fn(string $s) => 'badge badge-' . str_replace('_', '-', $s);
?>

<section class="page-header">
    <h1>Dealers</h1>
    <?php if ($canMutate): ?>
        <a href="/dealers/create" class="btn btn-primary">+ Buat Dealer</a>
    <?php endif; ?>
</section>

<form method="get" action="/dealers" class="card filter-bar">
    <div class="filter-grid">
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="">- Semua -</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= (($filters['status'] ?? '') === $s) ? 'selected' : '' ?>>
                        <?= e($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field-grow">
            <label>Cari</label>
            <input type="search" name="q" placeholder="Nama / kode / area"
                   value="<?= e((string) ($filters['q'] ?? '')) ?>">
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a href="/dealers" class="btn">Reset</a>
        <span class="muted">Total <?= count($rows) ?> dealer</span>
    </div>
</form>

<?php if ($rows === []): ?>
    <p class="muted">Belum ada dealer sesuai filter.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Area</th>
                    <th>PIC</th>
                    <th>Status</th>
                    <?php if ($canMutate): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e((string) ($r['code'] ?? '')) ?: '<span class="muted">-</span>' ?></td>
                        <td><?= e((string) $r['name']) ?></td>
                        <td><?= e((string) ($r['area'] ?? '')) ?: '<span class="muted">-</span>' ?></td>
                        <td>
                            <?php if (!empty($r['pic_name'])): ?>
                                <?= e((string) $r['pic_name']) ?>
                                <?php if (!empty($r['pic_phone'])): ?>
                                    <div class="muted"><?= e((string) $r['pic_phone']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="<?= $badge((string) $r['status']) ?>"><?= e((string) $r['status']) ?></span></td>
                        <?php if ($canMutate): ?>
                            <td class="row-actions">
                                <a href="/dealers/<?= (int) $r['id'] ?>/edit" class="btn btn-link">Edit</a>
                                <form method="post" action="/dealers/<?= (int) $r['id'] ?>/status" class="inline"
                                      onsubmit="return confirm('Ubah status dealer ini?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-link">
                                        <?= ((string) $r['status']) === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="cards-mobile">
        <?php foreach ($rows as $r): ?>
            <article class="card master-card">
                <div class="ticket-card-head">
                    <strong><?= e((string) $r['name']) ?></strong>
                    <span class="<?= $badge((string) $r['status']) ?>"><?= e((string) $r['status']) ?></span>
                </div>
                <div class="muted">
                    <?= e((string) ($r['code'] ?? '-')) ?> &middot;
                    <?= e((string) ($r['area'] ?? '-')) ?>
                </div>
                <?php if (!empty($r['pic_name'])): ?>
                    <div>PIC: <?= e((string) $r['pic_name']) ?>
                        <?php if (!empty($r['pic_phone'])): ?> &middot; <span class="muted"><?= e((string) $r['pic_phone']) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($canMutate): ?>
                    <div class="row-actions">
                        <a href="/dealers/<?= (int) $r['id'] ?>/edit" class="btn btn-link">Edit</a>
                        <form method="post" action="/dealers/<?= (int) $r['id'] ?>/status" class="inline"
                              onsubmit="return confirm('Ubah status dealer ini?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-link">
                                <?= ((string) $r['status']) === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
