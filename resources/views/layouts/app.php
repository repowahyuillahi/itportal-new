<?php
/** @var string $title */
/** @var string $content */
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'ITPortal') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <a class="skip-link" href="#content">Lewati ke konten</a>
    <header class="topbar">
        <div class="container">
            <a class="brand" href="/">ITPortal</a>
            <nav class="nav" aria-label="Navigasi utama">
                <?php if (\App\Core\Auth::check()): ?>
                    <a href="/dashboard">Dashboard</a>
                    <a href="/tickets">Tickets</a>
                    <a href="/dealers">Dealers</a>
                    <a href="/items">Items</a>
                    <a href="/reports/monthly">Reports</a>
                    <span class="muted">Hi, <?= e(\App\Core\Auth::user()['name'] ?? 'user') ?></span>
                    <form method="post" action="/logout" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-link">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="/login">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main id="content" class="container" tabindex="-1">
        <?php if ($flashError = flash('error')): ?>
            <div class="alert alert-error"><?= e($flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess = flash('success')): ?>
            <div class="alert alert-success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="footer">
        <div class="container muted">ITPortal &middot; PHP <?= e(PHP_VERSION) ?></div>
    </footer>
</body>
</html>
