<?php

declare(strict_types=1);

/**
 * Phase 8 dry-run: certificate import preview.
 *
 * Reads `data-lengkap-sertifikat.csv` and reports the canonical
 * certificate set, expiry status, and structural gaps (orphan certs,
 * duplicates, multiple-per-dealer). WRITES NOTHING.
 *
 * There is no live `dealer_certificates` table yet; the output ends with
 * the proposed schema so the owner can review columns before any
 * migration is committed.
 *
 *   php scripts/import_dryrun_certificates.php
 *   php scripts/import_dryrun_certificates.php --source=path\to\file.csv
 *   php scripts/import_dryrun_certificates.php --json
 *   php scripts/import_dryrun_certificates.php --show-paths      # show p12 basenames
 */

require __DIR__ . '/../bootstrap.php';

use App\Importers\CertificateCsvSource;
use App\Importers\CertificateImportDryRun;

$args = [];
$json = false;
$showPaths = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--json') { $json = true; continue; }
    if ($a === '--show-paths') { $showPaths = true; continue; }
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

$plan = (new CertificateImportDryRun())->plan($loaded['rows']);

if ($json) {
    // Redact p12 filenames from JSON unless --show-paths.
    $certs = $plan['certificates'];
    if (!$showPaths) {
        foreach ($certs as &$c) { $c['p12_filename'] = ''; }
        unset($c);
    }
    echo json_encode([
        'mode'            => 'dry-run',
        'writes_to_db'    => false,
        'source'          => realpath($source) ?: $source,
        'header_issues'   => $loaded['header_issues'],
        'summary'         => $plan['summary'],
        'certificates'    => $certs,
        'proposed_schema' => $plan['proposed_schema'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    echo "\n";
    exit(0);
}

$line = str_repeat('=', 70);
echo "$line\n";
echo "Certificate Import - DRY RUN (no writes)\n";
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
echo "  total_rows                : {$s['total_rows']}\n";
echo "  distinct_certificates     : {$s['distinct_certificates']}\n";
echo "  orphan_certificate        : {$s['orphan_certificate']}  (dealer_code not in DB)\n";
echo "  duplicate_cert_code       : {$s['duplicate_cert_code']}\n";
echo "  multiple_certs_per_dealer : {$s['multiple_certs_per_dealer']}\n";
echo "  expired                   : {$s['expired']}\n";
echo "  expiring_soon (<= 60d)    : {$s['expiring_soon']}\n";
echo "  pin_present / pin_blank   : {$s['pin_present']} / {$s['pin_blank']}\n\n";

echo "FLAGGED CERTIFICATES:\n";
$flagged = array_values(array_filter($plan['certificates'], static fn($c) => $c['flags'] !== []));
if ($flagged === []) {
    echo "  (none)\n";
} else {
    foreach ($flagged as $c) {
        echo sprintf("  row %4d  cert=%-10s  dealer=%-10s  flags=[%s]  valid_to=%s\n",
            $c['row_no'], $c['cert_code'], $c['dealer_code'],
            implode(',', $c['flags']),
            $c['valid_to'] !== '' ? $c['valid_to'] : '-');
    }
}

echo "\nFIRST 20 PARSED CERTIFICATES (preview):\n";
$preview = array_slice($plan['certificates'], 0, 20);
foreach ($preview as $c) {
    $p12 = $showPaths ? '  p12=' . $c['p12_filename'] : '';
    echo sprintf("  row %4d  %-10s -> dealer=%-10s  %-30s  valid %s -> %s  pin=%s%s\n",
        $c['row_no'], $c['cert_code'], $c['dealer_code'],
        substr($c['dealer_name'], 0, 28),
        $c['valid_from'] !== '' ? $c['valid_from'] : '-',
        $c['valid_to']   !== '' ? $c['valid_to']   : '-',
        $c['pin_masked'] !== '' ? $c['pin_masked'] : '(blank)',
        $p12);
}
if (count($plan['certificates']) > 20) {
    echo "  ... " . (count($plan['certificates']) - 20) . " more (use --json for full list)\n";
}

echo "\nPROPOSED SCHEMA (review before migrating):\n";
echo "  table: dealer_certificates\n";
foreach ($plan['proposed_schema'] as $col) {
    echo sprintf("    %-18s  %-50s  %s\n", $col['name'], $col['type'], $col['note']);
}

echo "\n$line\n";
echo "Dry-run complete. NO database rows were modified.\n";
echo "No migration was generated. Approve the schema above before adding\n";
echo "`database/migrations/0XX_create_dealer_certificates_table.sql`.\n";
echo "$line\n";
exit(0);
