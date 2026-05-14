<?php

declare(strict_types=1);

/**
 * Dev-only seeder: a handful of placeholder dealers so the ticket form
 * has FK targets during development. Real dealer master data is managed
 * via the admin UI in Phase 5.
 *
 * Idempotent: skips dealers whose `code` already exists.
 *
 * @var PDO $pdo provided by scripts/seed.php
 */

/** @var PDO $pdo */

$dealers = [
    ['code' => 'DLR-DEMO-01', 'name' => 'Dealer Demo Jakarta', 'area' => 'Jakarta'],
    ['code' => 'DLR-DEMO-02', 'name' => 'Dealer Demo Bandung', 'area' => 'Bandung'],
    ['code' => 'DLR-DEMO-03', 'name' => 'Dealer Demo Surabaya', 'area' => 'Surabaya'],
];

$now = date('Y-m-d H:i:s');
$check = $pdo->prepare('SELECT id FROM dealers WHERE code = :code LIMIT 1');
$insert = $pdo->prepare(
    'INSERT INTO dealers (code, name, area, status, created_at, updated_at)
     VALUES (:code, :name, :area, "active", :c, :u)'
);

$inserted = 0;
$skipped = 0;
foreach ($dealers as $d) {
    $check->execute(['code' => $d['code']]);
    if ($check->fetchColumn()) {
        $skipped++;
        continue;
    }
    $insert->execute([
        'code' => $d['code'],
        'name' => $d['name'],
        'area' => $d['area'],
        'c' => $now,
        'u' => $now,
    ]);
    $inserted++;
}

echo "  dev_dealers: inserted=$inserted, skipped(existing)=$skipped\n";
