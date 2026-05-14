<?php
/**
 * @var int $status
 * @var string $heading
 * @var string $message
 */
?>

<section class="error-page">
    <div class="error-code"><?= e((string) $status) ?></div>
    <h1><?= e($heading) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <p>
        <a href="/" class="btn btn-primary">Kembali ke Beranda</a>
        <?php if (\App\Core\Auth::check()): ?>
            <a href="/dashboard" class="btn">Dashboard</a>
        <?php else: ?>
            <a href="/login" class="btn">Login</a>
        <?php endif; ?>
    </p>
</section>
