<?php
/**
 * @var array<string, mixed> $ticket
 * @var bool $canMutate
 */
$status = (string) ($ticket['status'] ?? '');
$badge = 'badge badge-' . str_replace('_', '-', $status);
$canClose = $canMutate && !in_array($status, ['closed', 'cancelled'], true);
?>

<section class="page-header">
    <h1>
        <?= e((string) ($ticket['ticket_number'] ?? '')) ?>
        <span class="<?= $badge ?>"><?= e(str_replace('_', ' ', $status)) ?></span>
    </h1>
    <div class="page-header-actions">
        <a href="/tickets" class="btn">&laquo; List</a>
        <?php if ($canMutate): ?>
            <a href="/tickets/<?= (int) $ticket['id'] ?>/edit" class="btn">Edit</a>
        <?php endif; ?>
        <?php if ($canClose): ?>
            <form method="post" action="/tickets/<?= (int) $ticket['id'] ?>/close" class="inline"
                  onsubmit="return confirm('Tutup ticket ini sekarang?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Close Ticket</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="card detail-grid">
    <div>
        <div class="muted">Tanggal Laporan</div>
        <div><?= e(format_date_id((string) ($ticket['report_date'] ?? ''))) ?></div>
    </div>
    <div>
        <div class="muted">Pelapor</div>
        <div><?= e((string) ($ticket['reporter_name'] ?? '')) ?></div>
    </div>
    <div>
        <div class="muted">Dealer</div>
        <div><?= e((string) ($ticket['dealer_name'] ?? '')) ?></div>
    </div>
    <div>
        <div class="muted">Item</div>
        <div><?= e((string) ($ticket['item_name'] ?? '')) ?></div>
    </div>
    <div>
        <div class="muted">Petugas</div>
        <div><?= e((string) ($ticket['assigned_user_name'] ?? '-')) ?></div>
    </div>
    <div>
        <div class="muted">Dibuat oleh</div>
        <div><?= e((string) ($ticket['creator_name'] ?? '-')) ?></div>
    </div>
    <div>
        <div class="muted">Mulai</div>
        <div><?= e(format_datetime_id((string) ($ticket['started_at'] ?? ''))) ?></div>
    </div>
    <div>
        <div class="muted">Selesai</div>
        <div><?= e(format_datetime_id((string) ($ticket['finished_at'] ?? ''))) ?></div>
    </div>
    <div>
        <div class="muted">Lead Time</div>
        <div><?= e(format_lead_time(isset($ticket['lead_time_seconds']) && $ticket['lead_time_seconds'] !== null ? (int) $ticket['lead_time_seconds'] : null)) ?: '-' ?></div>
    </div>
    <?php if (!empty($ticket['closed_at'])): ?>
        <div>
            <div class="muted">Closed At</div>
            <div><?= e(format_datetime_id((string) $ticket['closed_at'])) ?></div>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Laporan Awal</h2>
    <p class="prewrap"><?= e((string) ($ticket['initial_report'] ?? '')) ?></p>
</section>

<?php if (!empty($ticket['checking_notes'])): ?>
    <section class="card">
        <h2>Catatan Pengecekan</h2>
        <p class="prewrap"><?= e((string) $ticket['checking_notes']) ?></p>
    </section>
<?php endif; ?>

<?php if (!empty($ticket['solution'])): ?>
    <section class="card">
        <h2>Solusi</h2>
        <p class="prewrap"><?= e((string) $ticket['solution']) ?></p>
    </section>
<?php endif; ?>
