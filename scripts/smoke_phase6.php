<?php

declare(strict_types=1);

/**
 * Phase 6 smoke test: dashboard summary, monthly report preview, error
 * pages rendered through the layout, and basic a11y/mobile checks.
 *
 * Reuses the helper users created by phase 4/5 smokes; this script will
 * also recreate them if missing.
 */

$base = $argv[1] ?? 'http://127.0.0.1:8770';
$failures = 0;

function newJar(): string
{
    $jar = tempnam(sys_get_temp_dir(), 'itp-cookies-');
    register_shutdown_function(static fn() => @unlink($jar));
    return $jar;
}

function req(string $base, string $method, string $path, array $form, string $jar): array
{
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = (string) curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $hsize = $info['header_size'];
    $hdrText = substr($raw, 0, $hsize);
    $body = substr($raw, $hsize);
    $location = null;
    foreach (explode("\r\n", $hdrText) as $line) {
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, 9));
        }
    }
    return ['status' => (int) $info['http_code'], 'location' => $location, 'body' => $body];
}

function csrf(string $html): ?string
{
    return preg_match('/name="_csrf"\s+value="([0-9a-f]+)"/', $html, $m) ? $m[1] : null;
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    $mark = $ok ? '[ OK ]' : '[FAIL]';
    if (!$ok) { $failures++; }
    echo "  $mark $name" . ($detail !== '' ? "  -- $detail" : '') . "\n";
}

function loginAs(string $base, string $jar, string $email, string $password): bool
{
    $r = req($base, 'GET', '/login', [], $jar);
    $tok = csrf($r['body']);
    if ($tok === null) { return false; }
    $r = req($base, 'POST', '/login', ['_csrf' => $tok, 'email' => $email, 'password' => $password], $jar);
    return $r['status'] === 302 && $r['location'] === '/dashboard';
}

require __DIR__ . '/../bootstrap.php';
$pdo = App\Core\Database::pdo();

echo "=== Setup ===\n";

$ensureUser = static function (PDO $pdo, string $email, string $name, string $role, string $password): int {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetchColumn();
    if ($row) { return (int) $row; }
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO users (name,email,password_hash,role,status,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?)')
        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, 'active', $now, $now]);
    return (int) $pdo->lastInsertId();
};

$adminId  = $ensureUser($pdo, 'admin@itportal.local', 'Admin',          'admin',    'secret123');
$viewerId = $ensureUser($pdo, 'viewer@itportal.local','Viewer Tester',  'viewer',   'viewer123');
$itId     = $ensureUser($pdo, 'it@itportal.local',    'IT Staff Tester','it_staff', 'itstaff123');
check('admin user', $adminId > 0);
check('viewer user', $viewerId > 0);
check('it_staff user', $itId > 0);

// Resolve current period.
$year  = (int) date('Y');
$month = (int) date('n');

// Compute oracle counts directly from the DB for this period.
$oracle = [
    'total' => 0,
    'by_status' => ['open' => 0, 'in_progress' => 0, 'pending' => 0, 'closed' => 0, 'cancelled' => 0],
    'avg_lead' => null,
    'top_dealer' => null,
    'top_item' => null,
];
$stmt = $pdo->prepare(
    'SELECT status, COUNT(*) c FROM tickets
     WHERE YEAR(report_date) = ? AND MONTH(report_date) = ? GROUP BY status'
);
$stmt->execute([$year, $month]);
foreach ($stmt->fetchAll() as $r) {
    $oracle['by_status'][$r['status']] = (int) $r['c'];
    $oracle['total'] += (int) $r['c'];
}
$stmt = $pdo->prepare(
    'SELECT AVG(lead_time_seconds) FROM tickets
     WHERE YEAR(report_date) = ? AND MONTH(report_date) = ? AND status = "closed"
       AND lead_time_seconds IS NOT NULL'
);
$stmt->execute([$year, $month]);
$avg = $stmt->fetchColumn();
$oracle['avg_lead'] = ($avg === null || $avg === false) ? null : (int) round((float) $avg);

$stmt = $pdo->prepare(
    'SELECT d.name, COUNT(*) c FROM tickets t INNER JOIN dealers d ON d.id = t.dealer_id
     WHERE YEAR(t.report_date) = ? AND MONTH(t.report_date) = ?
     GROUP BY d.id, d.name ORDER BY c DESC, d.name ASC LIMIT 1'
);
$stmt->execute([$year, $month]);
$topDealerRow = $stmt->fetch();
$oracle['top_dealer'] = $topDealerRow ? ['name' => $topDealerRow['name'], 'c' => (int) $topDealerRow['c']] : null;

$stmt = $pdo->prepare(
    'SELECT i.name, COUNT(*) c FROM tickets t INNER JOIN items i ON i.id = t.item_id
     WHERE YEAR(t.report_date) = ? AND MONTH(t.report_date) = ?
     GROUP BY i.id, i.name ORDER BY c DESC, i.name ASC LIMIT 1'
);
$stmt->execute([$year, $month]);
$topItemRow = $stmt->fetch();
$oracle['top_item'] = $topItemRow ? ['name' => $topItemRow['name'], 'c' => (int) $topItemRow['c']] : null;

echo "  oracle: total={$oracle['total']}";
foreach ($oracle['by_status'] as $k => $v) echo " $k=$v";
echo " avg=" . ($oracle['avg_lead'] ?? 'null') . "\n";

// ---------- Anon: dashboard requires login ----------

echo "\n=== Anon ===\n";
$jarAnon = newJar();
$r = req($base, 'GET', '/dashboard', [], $jarAnon);
check('anon /dashboard -> /login', $r['status'] === 302 && $r['location'] === '/login');
$r = req($base, 'GET', '/reports/monthly', [], $jarAnon);
check('anon /reports/monthly -> /login', $r['status'] === 302 && $r['location'] === '/login');

// ---------- Helper to assert dashboard body for any role ----------
$assertDashboard = static function (string $jar, string $tag) use ($base, $oracle, $year, $month): array {
    $errs = 0;
    $r = req($base, 'GET', '/dashboard', [], $jar);
    if ($r['status'] !== 200) { echo "  [FAIL] $tag dashboard 200 -- got {$r['status']}\n"; return [1]; }
    if (strpos($r['body'], 'Total Ticket') === false) { echo "  [FAIL] $tag dashboard has Total Ticket label\n"; $errs++; }
    if (preg_match('#<div class="stat-value">' . $oracle['total'] . '</div>#', $r['body']) !== 1) {
        echo "  [FAIL] $tag total matches oracle ({$oracle['total']})\n"; $errs++;
    } else {
        echo "  [ OK ] $tag total matches oracle ({$oracle['total']})\n";
    }
    foreach ($oracle['by_status'] as $s => $count) {
        // Each status badge appears with its count in stat-card. The count
        // appears in <div class="stat-value">N</div> within an article.
        // Cheap check: count occurrences of badge label + presence of count.
        if (strpos($r['body'], 'badge-' . str_replace('_', '-', $s)) === false) {
            echo "  [FAIL] $tag status badge $s present\n"; $errs++;
        }
    }
    // Skip-to-content link.
    if (strpos($r['body'], 'class="skip-link"') === false) {
        echo "  [FAIL] $tag skip-link present\n"; $errs++;
    }
    if (strpos($r['body'], 'id="content"') === false) {
        echo "  [FAIL] $tag main#content present\n"; $errs++;
    }
    return [$errs];
};

// ---------- Admin role ----------
echo "\n=== Admin role ===\n";
$jarA = newJar();
check('admin login', loginAs($base, $jarA, 'admin@itportal.local', 'secret123'));
$r = req($base, 'GET', '/dashboard', [], $jarA);
check('admin /dashboard 200', $r['status'] === 200);
check('admin sees skip-link', strpos($r['body'], 'class="skip-link"') !== false);
check('admin sees main#content', strpos($r['body'], 'id="content"') !== false);
check('total matches oracle (admin)',
    preg_match('#<div class="stat-value">' . $oracle['total'] . '</div>#', $r['body']) === 1,
    "oracle={$oracle['total']}");

foreach ($oracle['by_status'] as $s => $count) {
    check("status badge $s present", strpos($r['body'], 'badge-' . str_replace('_', '-', $s)) !== false);
}

if ($oracle['avg_lead'] !== null) {
    check('avg lead time visible',
        strpos($r['body'], 'Avg Lead Time') !== false);
}
if ($oracle['top_dealer']) {
    check('top dealer name visible',
        strpos($r['body'], (string) $oracle['top_dealer']['name']) !== false,
        "expected={$oracle['top_dealer']['name']}");
}
if ($oracle['top_item']) {
    check('top item name visible',
        strpos($r['body'], (string) $oracle['top_item']['name']) !== false,
        "expected={$oracle['top_item']['name']}");
}

// Filter month/year propagation (use a different month to ensure form repopulates).
$otherYear = $year - 1;
$r = req($base, 'GET', "/dashboard?year=$otherYear&month=1", [], $jarA);
check('dashboard filter month/year 200', $r['status'] === 200);
check('dashboard period label updated',
    strpos($r['body'], 'Januari ' . $otherYear) !== false,
    "expected period label");

// ---------- it_staff role ----------
echo "\n=== it_staff role ===\n";
$jarIT = newJar();
check('it_staff login', loginAs($base, $jarIT, 'it@itportal.local', 'itstaff123'));
$r = req($base, 'GET', '/dashboard', [], $jarIT);
check('it_staff /dashboard 200', $r['status'] === 200);
check('it_staff total matches oracle',
    preg_match('#<div class="stat-value">' . $oracle['total'] . '</div>#', $r['body']) === 1);

// ---------- viewer role ----------
echo "\n=== viewer role ===\n";
$jarV = newJar();
check('viewer login', loginAs($base, $jarV, 'viewer@itportal.local', 'viewer123'));
$r = req($base, 'GET', '/dashboard', [], $jarV);
check('viewer /dashboard 200', $r['status'] === 200);
check('viewer total matches oracle',
    preg_match('#<div class="stat-value">' . $oracle['total'] . '</div>#', $r['body']) === 1);
check('viewer sees Reports link', strpos($r['body'], 'href="/reports/monthly"') !== false);

// ---------- Reports preview ----------
echo "\n=== /reports/monthly ===\n";
$r = req($base, 'GET', '/reports/monthly', [], $jarA);
check('admin /reports/monthly 200', $r['status'] === 200);
// Export buttons: Phase 6 shipped these as disabled placeholders, but
// Phase 7 activated them as <a> links to /exports/monthly/*. We only
// assert they're present somehow (button OR link) so this script keeps
// working across phases.
check('export Excel control present',
    preg_match('/Export Excel/i', $r['body']) === 1);
check('export PDF control present',
    preg_match('/Export PDF/i', $r['body']) === 1);

// Filter status -> only matching rows (basic sanity).
$r = req($base, 'GET', '/reports/monthly?status=closed&month=' . $month . '&year=' . $year, [], $jarA);
check('reports filter status=closed 200', $r['status'] === 200);
$closedCount = (int) ($oracle['by_status']['closed'] ?? 0);
// status badge "closed" should appear as many times as closed tickets in body.
$matches = preg_match_all('/badge-closed/', $r['body']);
check("reports closed badges count >= closed total ($closedCount)", $matches >= $closedCount,
    "found=$matches closed=$closedCount");

// Filter dealer/item/q (use first available dealer/item).
$d1 = (int) ($pdo->query("SELECT id FROM dealers ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
$i1 = (int) ($pdo->query("SELECT id FROM items   ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
$r = req($base, 'GET', "/reports/monthly?dealer_id=$d1&item_id=$i1&q=smoke", [], $jarA);
check('reports compound filter 200', $r['status'] === 200);

// Empty-data resilience: pick a far-future month with no tickets.
$r = req($base, 'GET', '/reports/monthly?month=1&year=2099', [], $jarA);
check('reports empty period 200', $r['status'] === 200);
check('reports empty period shows empty message',
    strpos($r['body'], 'Tidak ada ticket') !== false);
$r = req($base, 'GET', '/dashboard?month=1&year=2099', [], $jarA);
check('dashboard empty period 200', $r['status'] === 200);
check('dashboard empty period total = 0',
    preg_match('#<div class="stat-value">0</div>#', $r['body']) === 1);

// ---------- Error pages via layout ----------
echo "\n=== Error pages in layout ===\n";

// 404 unknown route.
$r = req($base, 'GET', '/this-route-does-not-exist-' . time(), [], $jarA);
check('404 status', $r['status'] === 404);
check('404 wrapped in layout (topbar)', strpos($r['body'], 'class="topbar"') !== false);
check('404 has skip-link', strpos($r['body'], 'class="skip-link"') !== false);
check('404 shows code', strpos($r['body'], 'class="error-code"') !== false
    && strpos($r['body'], '>404<') !== false);

// 404 unknown ticket id.
$r = req($base, 'GET', '/tickets/999999999', [], $jarA);
check('ticket 404 wrapped in layout', $r['status'] === 404
    && strpos($r['body'], 'class="topbar"') !== false
    && strpos($r['body'], 'class="error-code"') !== false);

// 419 CSRF mismatch (POST without _csrf to a CSRF-protected route).
$r = req($base, 'POST', '/dealers', ['name' => 'no csrf'], $jarA);
check('419 status', $r['status'] === 419);
check('419 wrapped in layout', strpos($r['body'], 'class="topbar"') !== false
    && strpos($r['body'], '>419<') !== false);

// 403 role rejection (viewer tries admin-only page).
$r = req($base, 'GET', '/dealers/create', [], $jarV);
check('403 status', $r['status'] === 403);
check('403 wrapped in layout', strpos($r['body'], 'class="topbar"') !== false
    && strpos($r['body'], '>403<') !== false);

// /health unaffected.
$r = req($base, 'GET', '/health', [], newJar());
$json = json_decode($r['body'], true);
check('/health db: ok', $r['status'] === 200 && ($json['db'] ?? '') === 'ok');

echo "\n================================================\n";
if ($failures === 0) { echo "ALL PASSED\n"; exit(0); }
echo "FAILED: $failures check(s)\n";
exit(1);
