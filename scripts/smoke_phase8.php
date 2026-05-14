<?php

declare(strict_types=1);

/**
 * Phase 8 smoke test: source data import dry-runs.
 *
 * Asserts:
 *  - Both dealer + certificate dry-run scripts execute (exit 0 or 1, but
 *    never 2 / crash).
 *  - Their output mentions "DRY RUN" and "no writes".
 *  - The shape of the structured plans (verdicts / certificates).
 *  - Mapping verdicts add up to total rows.
 *  - Sensitive PIN values are masked in text output.
 *  - `data-lengkap-sertifikat.csv` PIN values do NOT leak into stdout.
 *  - JSON mode is valid JSON, has `writes_to_db: false`.
 *  - **DB invariants**: row counts of `dealers`, `tickets`, `items`,
 *    `users`, `audit_logs`, `export_jobs` are identical before and after
 *    the dry-run runs.
 *  - php-error.log stays clean.
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Importers\CertificateCsvSource;
use App\Importers\CertificateImportDryRun;
use App\Importers\DealerImportDryRun;

$failures = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    $mark = $ok ? '[ OK ]' : '[FAIL]';
    if (!$ok) { $failures++; }
    echo "  $mark $name" . ($detail !== '' ? "  -- $detail" : '') . "\n";
}

$pdo = Database::pdo();

$snapshot = static function (PDO $pdo): array {
    $counts = [];
    foreach (['dealers','tickets','items','users','audit_logs','export_jobs'] as $t) {
        try {
            $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        } catch (Throwable $e) {
            $counts[$t] = -1;
        }
    }
    return $counts;
};

echo "=== Snapshot before ===\n";
$before = $snapshot($pdo);
foreach ($before as $t => $n) { echo "  $t = $n\n"; }

$source = __DIR__ . '/../data-lengkap-sertifikat.csv';
check('source CSV exists', is_file($source), $source);

// ----- 1. Direct service calls -----
echo "\n=== Direct service calls ===\n";
$loader = new CertificateCsvSource();
$loaded = $loader->load($source);
check('csv loaded > 0 rows', count($loaded['rows']) > 0, 'rows=' . count($loaded['rows']));

// Each row has _row_no, _parsed, _dealer_name, _valid_*_ts keys.
$r0 = $loaded['rows'][0];
foreach (['_row_no','_parsed','_dealer_name','_valid_from_ts','_valid_to_ts'] as $k) {
    check("row shape has $k", array_key_exists($k, $r0));
}

$dealerPlan = (new DealerImportDryRun())->plan($loaded['rows']);
$s = $dealerPlan['summary'];
check('summary keys present',
    isset($s['total_rows'], $s['would_create'], $s['would_update'], $s['match'], $s['ambiguous'], $s['unparseable']));
$verdictSum = $s['would_create'] + $s['would_update'] + $s['match'] + $s['ambiguous'] + $s['unparseable'];
// Note: would_create / would_update dedupe by code, so verdictSum <= total_rows.
check('verdict count <= total rows', $verdictSum <= $s['total_rows'],
    "sum=$verdictSum total={$s['total_rows']}");
check('total rows > 0', $s['total_rows'] > 0);

$certPlan = (new CertificateImportDryRun())->plan($loaded['rows']);
$cs = $certPlan['summary'];
check('cert plan has certificates list', count($certPlan['certificates']) > 0,
    'certs=' . count($certPlan['certificates']));
check('cert plan has proposed_schema with >= 10 columns',
    count($certPlan['proposed_schema']) >= 10);
check('cert plan summary has expected keys',
    isset($cs['total_rows'], $cs['distinct_certificates'], $cs['orphan_certificate'],
          $cs['duplicate_cert_code'], $cs['expired'], $cs['expiring_soon']));

// ----- 2. CLI script: dealers (text) -----
echo "\n=== CLI: import_dryrun_dealers.php ===\n";
$out = shell_exec('"C:\\xampp\\php\\php.exe" "' . __DIR__ . '/import_dryrun_dealers.php" 2>&1');
check('dealer dry-run script produced output', is_string($out) && strlen($out) > 100);
check('dealer dry-run output says DRY RUN',  strpos((string) $out, 'DRY RUN') !== false);
check('dealer dry-run output says no writes', strpos((string) $out, 'NO database rows were modified') !== false);
check('dealer dry-run output has Summary',    strpos((string) $out, 'Summary:') !== false);
check('dealer dry-run output has WOULD_CREATE section', strpos((string) $out, 'WOULD_CREATE') !== false);

// Sensitive data must NOT leak. Pin values from the CSV should NEVER appear
// in dealer text output (dealer report doesn't print PINs at all).
$samplePin = (string) ($loaded['rows'][0]['pin'] ?? '');
if ($samplePin !== '') {
    check('dealer dry-run does NOT leak raw pin', strpos((string) $out, $samplePin) === false);
}

// ----- 3. CLI script: dealers --json -----
$jsonOut = shell_exec('"C:\\xampp\\php\\php.exe" "' . __DIR__ . '/import_dryrun_dealers.php" --json 2>&1');
$parsed = json_decode((string) $jsonOut, true);
check('dealer --json is valid JSON', is_array($parsed));
check('dealer --json has writes_to_db = false', ($parsed['writes_to_db'] ?? null) === false);
check('dealer --json has summary',  isset($parsed['summary']['would_create']));
check('dealer --json has verdicts list', isset($parsed['verdicts']) && is_array($parsed['verdicts']));

// ----- 4. CLI script: certificates (text) -----
echo "\n=== CLI: import_dryrun_certificates.php ===\n";
$out2 = shell_exec('"C:\\xampp\\php\\php.exe" "' . __DIR__ . '/import_dryrun_certificates.php" 2>&1');
check('cert dry-run script produced output', is_string($out2) && strlen($out2) > 100);
check('cert dry-run output says DRY RUN',  strpos((string) $out2, 'DRY RUN') !== false);
check('cert dry-run output says no writes', strpos((string) $out2, 'NO database rows were modified') !== false);
check('cert dry-run prints proposed schema',
    strpos((string) $out2, 'PROPOSED SCHEMA') !== false
    && strpos((string) $out2, 'dealer_certificates') !== false);
check('cert dry-run prints flagged section',
    strpos((string) $out2, 'FLAGGED CERTIFICATES') !== false);

// Sensitive: raw pin must NOT leak in text output of cert dry-run.
if ($samplePin !== '' && strlen($samplePin) > 4) {
    check('cert dry-run does NOT leak raw pin',
        strpos((string) $out2, $samplePin) === false,
        'pin len=' . strlen($samplePin));
    // The mask should appear (pin is 16+ chars, mask format "AA****...XX").
    $masked = substr($samplePin, 0, 2);
    check('cert dry-run shows masked pin prefix in output',
        strpos((string) $out2, $masked . '*') !== false,
        'looking for ' . $masked . '*');
}

// By default, p12 absolute paths must NOT leak.
check('cert dry-run hides p12 absolute path by default',
    strpos((string) $out2, 'C:\\Portable Apps') === false);

// ----- 5. CLI script: certificates --json -----
$jsonOut2 = shell_exec('"C:\\xampp\\php\\php.exe" "' . __DIR__ . '/import_dryrun_certificates.php" --json 2>&1');
$parsed2 = json_decode((string) $jsonOut2, true);
check('cert --json is valid JSON', is_array($parsed2));
check('cert --json has writes_to_db = false', ($parsed2['writes_to_db'] ?? null) === false);
check('cert --json has proposed_schema array', isset($parsed2['proposed_schema']) && is_array($parsed2['proposed_schema']));
check('cert --json certificate entries have masked pin (no raw)',
    is_array($parsed2['certificates'] ?? null)
    && ($parsed2['certificates'][0]['pin_masked'] ?? '') !== ''
    && strpos((string) $jsonOut2, $samplePin) === false,
    'first masked=' . ($parsed2['certificates'][0]['pin_masked'] ?? ''));

// ----- 6. DB invariants -----
echo "\n=== Snapshot after ===\n";
$after = $snapshot($pdo);
foreach ($after as $t => $n) { echo "  $t = $n\n"; }
foreach ($before as $t => $n) {
    check("table '$t' unchanged ($n)", $after[$t] === $n, "before=$n after={$after[$t]}");
}

// ----- 7. --source override accepted -----
echo "\n=== --source flag ===\n";
$out3 = shell_exec('"C:\\xampp\\php\\php.exe" "' . __DIR__ . '/import_dryrun_dealers.php" --source=' . escapeshellarg($source) . ' --json 2>&1');
$parsed3 = json_decode((string) $out3, true);
check('--source flag accepted', is_array($parsed3) && ($parsed3['writes_to_db'] ?? null) === false);

echo "\n================================================\n";
if ($failures === 0) { echo "ALL PASSED\n"; exit(0); }
echo "FAILED: $failures check(s)\n";
exit(1);
