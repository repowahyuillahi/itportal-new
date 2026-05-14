<?php
/** @var array<string, mixed> $user */
?>
<section class="card">
    <h1>Dashboard</h1>
    <p>
        Halo <strong><?= e($user['name'] ?? '') ?></strong>
        (<span class="muted"><?= e($user['email'] ?? '') ?></span>) —
        role <code><?= e($user['role'] ?? '') ?></code>.
    </p>
    <p class="muted">
        Ringkasan ticket dan kartu statistik akan diisi di Phase 6.
    </p>

    <ul>
        <li><a href="/tickets">Tickets</a> <span class="muted">(Phase 4)</span></li>
        <li><a href="/dealers">Dealers</a> <span class="muted">(Phase 5)</span></li>
        <li><a href="/items">Items</a> <span class="muted">(Phase 5)</span></li>
        <li><a href="/reports/monthly">Monthly Report</a> <span class="muted">(Phase 6)</span></li>
    </ul>
</section>
