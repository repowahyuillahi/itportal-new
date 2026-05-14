<?php
/**
 * @var string $mode 'create' | 'edit'
 * @var array<string, mixed> $dealer
 * @var array<int, string> $statuses
 * @var array<string, string> $errors
 */
$action = $mode === 'create' ? '/dealers' : '/dealers/' . (int) $dealer['id'];

$oldBag = (function (): array {
    $bag = \App\Core\Session::flash('_old');
    if ($bag === null) return [];
    $arr = json_decode($bag, true);
    return is_array($arr) ? $arr : [];
})();
$val = static function (string $key, mixed $default = '') use ($oldBag, $dealer): string {
    if (array_key_exists($key, $oldBag)) {
        return (string) $oldBag[$key];
    }
    return (string) ($dealer[$key] ?? $default);
};
$err = static fn(string $f) => $errors[$f] ?? null;
?>

<section class="page-header">
    <h1><?= $mode === 'create' ? 'Buat Dealer' : 'Edit Dealer' ?></h1>
    <a href="/dealers" class="btn">Batal</a>
</section>

<?php if (!empty($errors['_global'])): ?>
    <div class="alert alert-error"><?= e($errors['_global']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="card form">
    <?= csrf_field() ?>

    <div class="grid-2">
        <div class="field">
            <label>Kode <span class="muted">(opsional, unique)</span></label>
            <input type="text" name="code" maxlength="64" value="<?= e($val('code')) ?>">
            <?php if ($e = $err('code')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $val('status', 'active') === $s ? 'selected' : '' ?>>
                        <?= e($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="field">
        <label>Nama Dealer</label>
        <input type="text" name="name" maxlength="191" required value="<?= e($val('name')) ?>">
        <?php if ($e = $err('name')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
    </div>

    <div class="field">
        <label>Area</label>
        <input type="text" name="area" maxlength="120" value="<?= e($val('area')) ?>">
    </div>

    <div class="field">
        <label>Alamat</label>
        <textarea name="address" rows="2"><?= e($val('address')) ?></textarea>
    </div>

    <div class="grid-2">
        <div class="field">
            <label>Nama PIC</label>
            <input type="text" name="pic_name" maxlength="120" value="<?= e($val('pic_name')) ?>">
        </div>
        <div class="field">
            <label>Telepon PIC</label>
            <input type="text" name="pic_phone" maxlength="64" value="<?= e($val('pic_phone')) ?>">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $mode === 'create' ? 'Simpan Dealer' : 'Simpan Perubahan' ?>
        </button>
        <a href="/dealers" class="btn">Batal</a>
    </div>
</form>
