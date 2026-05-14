<?php
/**
 * PDF template for the monthly Support Maintenance Report (landscape A4).
 * Rendered by Dompdf - keep CSS conservative (Dompdf supports a subset).
 *
 * @var string $title
 * @var array<int, string> $columns
 * @var array<int, array<int, string>> $rows  pre-formatted cells in spec order
 * @var string $period
 * @var string $generated
 */
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
    @page { margin: 14mm 10mm 14mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1f2937; }
    h1 { font-size: 13pt; margin: 0 0 4px; text-align: center; }
    .meta { font-size: 8pt; color: #555; margin-bottom: 8px; text-align: center; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; }
    th, td {
        border: 0.5pt solid #999;
        padding: 3px 4px;
        vertical-align: top;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    th {
        background-color: #1F4E78;
        color: #ffffff;
        font-weight: bold;
        font-size: 8pt;
        text-align: center;
    }
    /* Column widths sum ~= 100% (landscape A4 usable ~277mm).
       1=No, 2=Status, 3=Tanggal, 4=Pelapor, 5=Dealer, 6=LaporanAwal,
       7=Pengecekan, 8=Solusi, 9=Item, 10=Mulai, 11=Selesai, 12=Lead */
    col.c1  { width: 3%; }
    col.c2  { width: 6%; }
    col.c3  { width: 6%; }
    col.c4  { width: 9%; }
    col.c5  { width: 10%; }
    col.c6  { width: 13%; }
    col.c7  { width: 13%; }
    col.c8  { width: 13%; }
    col.c9  { width: 8%; }
    col.c10 { width: 7%; }
    col.c11 { width: 7%; }
    col.c12 { width: 5%; }
    td.num { text-align: center; }
    td.mono { font-family: DejaVu Sans Mono, monospace; }
    .empty {
        text-align: center;
        padding: 18px;
        color: #666;
        font-style: italic;
    }
</style>
</head>
<body>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="meta">
        Periode: <?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?>
        &middot; <?= count($rows) ?> ticket
        &middot; Dicetak: <?= htmlspecialchars($generated, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <table>
        <colgroup>
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <col class="c<?= $i ?>">
            <?php endfor; ?>
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($columns as $h): ?>
                    <th><?= htmlspecialchars($h, ENT_QUOTES, 'UTF-8') ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr>
                    <td class="empty" colspan="12">Tidak ada ticket pada periode/filter ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="num"><?= htmlspecialchars($r[0], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r[1], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r[2], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r[3], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r[4], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= nl2br(htmlspecialchars($r[5], ENT_QUOTES, 'UTF-8')) ?></td>
                        <td><?= nl2br(htmlspecialchars($r[6], ENT_QUOTES, 'UTF-8')) ?></td>
                        <td><?= nl2br(htmlspecialchars($r[7], ENT_QUOTES, 'UTF-8')) ?></td>
                        <td><?= htmlspecialchars($r[8], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="mono"><?= htmlspecialchars($r[9], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="mono"><?= htmlspecialchars($r[10], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="mono"><?= htmlspecialchars($r[11], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
