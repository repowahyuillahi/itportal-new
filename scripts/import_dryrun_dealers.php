<?php

declare(strict_types=1);

/**
 * Phase 8 dry-run: dealer import preview.
 *
 * Reads `data-lengkap-sertifikat.csv` (default; override with --source=PATH)
 * and prints a diff against the live `dealers` table. WRITES NOTHING.
 *
 *   php scripts/import_dryrun_dealers.php
 *   php scripts/import_dryrun_dealers.php --source=path\to\file.csv
 *   php scripts/import_dryrun_dealers.php --json
 */

require __DIR__ . '/../bootstrap.php';

use App\Importers\CertificateCsvSource;
use App\Importers\DealerImportDryRun;

// Parse CLI flags.
$args = [];
$json = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--json') { $json = true; continue; }
    if (str_starts_with($a, '--source=')) { $args['source'] = substr($a, 9); continue; }
}

$source = $args['source'] ?? (__DIR__ . '/../data-lengkap-sertifikat.csv');

try {
    $loader = new CertificateCsvSource();
    $loaded = $loader->load($source);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(2);
}

$plan = (new DealerImportDryRun())->plan($loaded['rows']);

if ($json) {
    echo json_encode([
        'mode'           => 'dry-run',
        'writes_to_db'   => false,
        'source'         => realpath($source) ?: $source,
        'header_issues'  => $loaded['header_issues'],
        'summary'        => $plan['summary'],
        'verdicts'       => $plan['verdicts'],
        'ambiguous_groups' => $plan['ambiguous_groups'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    echo "\n";
    exit(0);
}

// Text report.
$line = str_repeat('=', 70);
echo "$line\n";
echo "Dealer Import - DRY RUN (no writes)\n";
echo "Source : " . (realpath($source) ?: $source) . "\n";
echo "Time   : " . date('Y-m-d H:i:s T') . "\n";
echo "$line\n\n";

if ($loaded['header_issues'] !== []) {
    echo "HEADER ISSUES:\n";
    foreach ($loaded['header_issues'] as $i) {
        echo "  - $i\n";
    }
    echo "\n";
}

$s = $plan['summary'];
echo "Summary:\n";
echo "  total_rows    : {$s['total_rows']}\n";
echo "  would_create  : {$s['would_create']}\n";
echo "  would_update  : {$s['would_update']}\n";
echo "  match         : {$s['match']}\n";
echo "  ambiguous     : {$s['ambiguous']}\n";
echo "  unparseable   : {$s['unparseable']}\n\n";

$groupBy = static function (array $verdicts, string $wanted): array {
    return array_values(array_filter($verdicts, static fn($v) => $v['verdict'] === $wanted));
};

$printRows = static function (array $rows, callable $line): void {
    if ($rows === []) { echo "  (none)\n"; return; }
    foreach ($rows as $r) { echo "  " . $line($r) . "\n"; }
};

echo "WOULD_CREATE (will be INSERTed when apply mode is approved):\n";
$printRows($groupBy($plan['verdicts'], 'would_create'), static fn($v) =>
    sprintf('row %4d  code=%-10s name=%s  area=%s',
        $v['row_no'], $v['code'], $v['name'], $v['area']));

echo "\nWOULD_UPDATE (UPDATE in apply mode; existing dealer differs from CSV):\n";
$printRows($groupBy($plan['verdicts'], 'would_update'), static function ($v) {
    $diffs = [];
    foreach ($v['diff'] as $field => $d) {
        $diffs[] = "$field: " . ($d['from'] === '' ? '(empty)' : $d['from'])
                 . " -> " . ($d['to'] === '' ? '(empty)' : $d['to']);
    }
    return sprintf('row %4d  code=%-10s  id=%d  [%s]',
        $v['row_no'], $v['code'], $v['current']['id'], implode(' | ', $diffs));
});

echo "\nAMBIGUOUS (CSV self-conflict; resolve in source before apply):\n";
$printRows($groupBy($plan['verdicts'], 'ambiguous'), static fn($v) =>
    sprintf('row %4d  code=%-10s name=%s area=%s  -- %s',
        $v['row_no'], $v['code'], $v['name'], $v['area'], $v['reason']));

echo "\nUNPARSEABLE (dropped from plan):\n";
$printRows($groupBy($plan['verdicts'], 'unparseable'), static fn($v) =>
    sprintf('row %4d  code=%-10s name=%s area=%s  -- %s',
        $v['row_no'], $v['code'] !== '' ? $v['code'] : '(blank)', $v['name'], $v['area'], $v['reason']));

echo "\nMATCH (already canonical; nothing to do): {$s['match']} rows\n";

echo "\n$line\n";
echo "Dry-run complete. NO database rows were modified.\n";
echo "$line\n";
exit($s['ambiguous'] > 0 ? 1 : 0);
