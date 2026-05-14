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
    <h1>Items</h1>
    <?php if ($canMutate): ?>
        <a href="/items/create" class="btn btn-primary">+ Buat Item</a>
    <?php endif; ?>
</section>

<form method="get" action="/items" class="card filter-bar">
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
            <input type="search" name="q" placeholder="Nama atau slug"
                   value="<?= e((string) ($filters['q'] ?? '')) ?>">
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a href="/items" class="btn">Reset</a>
        <span class="muted">Total <?= count($rows) ?> item</span>
    </div>
</form>

<?php if ($rows === []): ?>
    <p class="muted">Belum ada item sesuai filter.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Urut</th>
                    <th>Nama</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <?php if ($canMutate): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int) ($r['sort_order'] ?? 0) ?></td>
                        <td><?= e((string) $r['name']) ?></td>
                        <td><code><?= e((string) $r['slug']) ?></code></td>
                        <td><span class="<?= $badge((string) $r['status']) ?>"><?= e((string) $r['status']) ?></span></td>
                        <?php if ($canMutate): ?>
                            <td class="row-actions">
                                <a href="/items/<?= (int) $r['id'] ?>/edit" class="btn btn-link">Edit</a>
                                <form method="post" action="/items/<?= (int) $r['id'] ?>/status" class="inline"
                                      onsubmit="return confirm('Ubah status item ini?');">
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
                    slug: <code><?= e((string) $r['slug']) ?></code>
                    &middot; urut <?= (int) ($r['sort_order'] ?? 0) ?>
                </div>
                <?php if ($canMutate): ?>
                    <div class="row-actions">
                        <a href="/items/<?= (int) $r['id'] ?>/edit" class="btn btn-link">Edit</a>
                        <form method="post" action="/items/<?= (int) $r['id'] ?>/status" class="inline"
                              onsubmit="return confirm('Ubah status item ini?');">
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
