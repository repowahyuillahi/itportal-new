<?php

declare(strict_types=1);

/**
 * Seed default `items` master from docs/data-model.md.
 *
 * Idempotent: skip rows whose slug already exists.
 *
 * Run via scripts/seed.php (loaded automatically).
 *
 * @var PDO $pdo provided by scripts/seed.php
 */

/** @var PDO $pdo */

$items = [
    'Hardware',
    'Software',
    'Printer',
    'Network',
    'Internet',
    'CCTV',
    'Server',
    'Backup',
    'YDT',
    'DpackWeb',
    'Radio',
    'Zahir',
    'Fingerprint Absensi',
    'Kabel Listrik dan Adaptor',
    'Other',
];

$slugify = static function (string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
    return trim($s, '-');
};

$now = date('Y-m-d H:i:s');

$check = $pdo->prepare('SELECT id FROM items WHERE slug = :slug LIMIT 1');
$insert = $pdo->prepare(
    'INSERT INTO items (name, slug, description, status, sort_order, created_at, updated_at)
     VALUES (:name, :slug, NULL, "active", :sort, :created, :updated)'
);

$inserted = 0;
$skipped = 0;
$sort = 10;
foreach ($items as $name) {
    $slug = $slugify($name);
    $check->execute(['slug' => $slug]);
    if ($check->fetchColumn()) {
        $skipped++;
        $sort += 10;
        continue;
    }
    $insert->execute([
        'name' => $name,
        'slug' => $slug,
        'sort' => $sort,
        'created' => $now,
        'updated' => $now,
    ]);
    $inserted++;
    $sort += 10;
}

echo "  items: inserted=$inserted, skipped(existing)=$skipped\n";
