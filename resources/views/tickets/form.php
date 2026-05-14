<?php
/**
 * @var string $mode 'create' | 'edit'
 * @var array<string, mixed> $ticket
 * @var array<int, array<string, mixed>> $dealers
 * @var array<int, array<string, mixed>> $items
 * @var array<int, string> $statuses
 * @var array<string, string> $errors
 */

$action = $mode === 'create' ? '/tickets' : '/tickets/' . (int) $ticket['id'];
$cancel = $mode === 'create' ? '/tickets' : '/tickets/' . (int) $ticket['id'];

// Pull old() for each field once. old() drains the flash bag, so call only one place.
$oldBag = (function (): array {
    $bag = \App\Core\Session::flash('_old');
    if ($bag === null) return [];
    $arr = json_decode($bag, true);
    return is_array($arr) ? $arr : [];
})();
$val = static function (string $key, mixed $default = '') use ($oldBag, $ticket): string {
    if (array_key_exists($key, $oldBag)) {
        return (string) $oldBag[$key];
    }
    return (string) ($ticket[$key] ?? $default);
};
$err = static fn(string $f) => $errors[$f] ?? null;

// HTML datetime-local needs `YYYY-MM-DDTHH:MM`. Convert from DB style if needed.
$dt = static function (string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    return substr(str_replace(' ', 'T', $s), 0, 16);
};
?>

<section class="page-header">
    <h1><?= $mode === 'create' ? 'Buat Ticket' : 'Edit Ticket ' . e((string) ($ticket['ticket_number'] ?? '')) ?></h1>
    <a href="<?= e($cancel) ?>" class="btn">Batal</a>
</section>

<?php if (!empty($errors['_global'])): ?>
    <div class="alert alert-error"><?= e($errors['_global']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="card form ticket-form">
    <?= csrf_field() ?>

    <div class="grid-2">
        <div class="field">
            <label>Tanggal Laporan</label>
            <input type="date" name="report_date" required value="<?= e($val('report_date')) ?>">
            <?php if ($e = $err('report_date')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $val('status', 'open') === $s ? 'selected' : '' ?>>
                        <?= e(str_replace('_', ' ', $s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($e = $err('status')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
    </div>

    <div class="field">
        <label>Nama Pelapor</label>
        <input type="text" name="reporter_name" maxlength="150" required
               value="<?= e($val('reporter_name')) ?>">
        <?php if ($e = $err('reporter_name')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
    </div>

    <div class="grid-2">
        <div class="field">
            <label>Dealer</label>
            <select name="dealer_id" required>
                <option value="">- pilih -</option>
                <?php foreach ($dealers as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (int) $val('dealer_id') === (int) $d['id'] ? 'selected' : '' ?>>
                        <?= e((string) $d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($e = $err('dealer_id')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
        <div class="field">
            <label>Item</label>
            <select name="item_id" required>
                <option value="">- pilih -</option>
                <?php foreach ($items as $it): ?>
                    <option value="<?= (int) $it['id'] ?>" <?= (int) $val('item_id') === (int) $it['id'] ? 'selected' : '' ?>>
                        <?= e((string) $it['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($e = $err('item_id')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
    </div>

    <div class="field">
        <label>Laporan Awal</label>
        <textarea name="initial_report" rows="3" required><?= e($val('initial_report')) ?></textarea>
        <?php if ($e = $err('initial_report')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
    </div>

    <div class="grid-2">
        <div class="field">
            <label>Waktu Mulai</label>
            <input type="datetime-local" name="started_at" required
                   value="<?= e($dt($val('started_at'))) ?>">
            <?php if ($e = $err('started_at')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
        <div class="field">
            <label>Waktu Selesai <span class="muted">(opsional)</span></label>
            <input type="datetime-local" name="finished_at"
                   value="<?= e($dt($val('finished_at'))) ?>">
            <?php if ($e = $err('finished_at')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
    </div>

    <div class="field">
        <label>Catatan Pengecekan</label>
        <textarea name="checking_notes" rows="3"><?= e($val('checking_notes')) ?></textarea>
    </div>

    <div class="field">
        <label>Solusi</label>
        <textarea name="solution" rows="3"><?= e($val('solution')) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $mode === 'create' ? 'Simpan Ticket' : 'Simpan Perubahan' ?>
        </button>
        <a href="<?= e($cancel) ?>" class="btn">Batal</a>
    </div>
</form>
