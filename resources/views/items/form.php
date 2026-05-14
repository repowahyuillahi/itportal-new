<?php
/**
 * @var string $mode
 * @var array<string, mixed> $item
 * @var array<int, string> $statuses
 * @var array<string, string> $errors
 */
$action = $mode === 'create' ? '/items' : '/items/' . (int) $item['id'];

$oldBag = (function (): array {
    $bag = \App\Core\Session::flash('_old');
    if ($bag === null) return [];
    $arr = json_decode($bag, true);
    return is_array($arr) ? $arr : [];
})();
$val = static function (string $key, mixed $default = '') use ($oldBag, $item): string {
    if (array_key_exists($key, $oldBag)) {
        return (string) $oldBag[$key];
    }
    return (string) ($item[$key] ?? $default);
};
$err = static fn(string $f) => $errors[$f] ?? null;
?>

<section class="page-header">
    <h1><?= $mode === 'create' ? 'Buat Item' : 'Edit Item' ?></h1>
    <a href="/items" class="btn">Batal</a>
</section>

<?php if (!empty($errors['_global'])): ?>
    <div class="alert alert-error"><?= e($errors['_global']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="card form">
    <?= csrf_field() ?>

    <div class="field">
        <label>Nama</label>
        <input type="text" name="name" maxlength="191" required value="<?= e($val('name')) ?>">
        <?php if ($e = $err('name')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
    </div>

    <div class="grid-2">
        <div class="field">
            <label>Slug <span class="muted">(opsional, otomatis dari nama)</span></label>
            <input type="text" name="slug" maxlength="191" value="<?= e($val('slug')) ?>">
            <?php if ($e = $err('slug')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
        <div class="field">
            <label>Urutan</label>
            <input type="number" name="sort_order" min="0" max="9999" value="<?= e($val('sort_order')) ?>">
            <?php if ($e = $err('sort_order')): ?><small class="error"><?= e($e) ?></small><?php endif; ?>
        </div>
    </div>

    <div class="field">
        <label>Deskripsi</label>
        <textarea name="description" rows="3"><?= e($val('description')) ?></textarea>
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

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $mode === 'create' ? 'Simpan Item' : 'Simpan Perubahan' ?>
        </button>
        <a href="/items" class="btn">Batal</a>
    </div>
</form>
