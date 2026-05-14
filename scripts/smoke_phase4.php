<?php

declare(strict_types=1);

/**
 * Phase 4 smoke test for /tickets routes.
 *
 * Requires:
 *   - Migrations run.
 *   - At least one admin user (admin@itportal.local / secret123).
 *   - At least one dealer + item (run scripts/seed.php beforehand).
 *   - The dev server running on the BASE_URL passed in.
 */

$base = $argv[1] ?? 'http://127.0.0.1:8768';
$failures = 0;

// ---------- shared helpers ----------

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

// Bootstrap to get DB lookups.
require __DIR__ . '/../bootstrap.php';
$pdo = App\Core\Database::pdo();

// ---------- ensure test users + dealer + item exist ----------

echo "=== Setup ===\n";

// Admin (from create_admin.php in Phase 3).
$adminId = (int) ($pdo->query("SELECT id FROM users WHERE email='admin@itportal.local'")->fetchColumn() ?: 0);
check('admin user exists', $adminId > 0, 'run scripts/create_admin.php first');

// Viewer test user (created here).
$viewerEmail = 'viewer@itportal.local';
$viewerPass = 'viewer123';
$viewerId = (int) ($pdo->query("SELECT id FROM users WHERE email=" . $pdo->quote($viewerEmail))->fetchColumn() ?: 0);
if ($viewerId === 0) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO users (name,email,password_hash,role,status,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute(['Viewer Tester', $viewerEmail, password_hash($viewerPass, PASSWORD_DEFAULT),
                    'viewer', 'active', $now, $now]);
    $viewerId = (int) $pdo->lastInsertId();
}
check('viewer user ready', $viewerId > 0);

$dealerId = (int) ($pdo->query("SELECT id FROM dealers WHERE status='active' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
$itemId   = (int) ($pdo->query("SELECT id FROM items   WHERE status='active' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
check('dealer present', $dealerId > 0);
check('item present', $itemId > 0);

// ---------- ADMIN flow ----------

$jarA = newJar();
echo "\n=== Admin flow ===\n";
check('admin login', loginAs($base, $jarA, 'admin@itportal.local', 'secret123'));

// Anon (unauth) sanity: /tickets should redirect to /login.
$jarAnon = newJar();
$r = req($base, 'GET', '/tickets', [], $jarAnon);
check('unauth /tickets -> /login', $r['status'] === 302 && $r['location'] === '/login', "status={$r['status']}");

// CREATE ticket as admin.
echo "[T1] Admin create ticket\n";
$r = req($base, 'GET', '/tickets/create', [], $jarA);
$tok = csrf($r['body']);
check('create form 200', $r['status'] === 200);
check('create form CSRF', $tok !== null);

$today = date('Y-m-d');
$started = date('Y-m-d\TH:i', strtotime('-2 hours'));
$finished = date('Y-m-d\TH:i'); // 2h after started
$payload = [
    '_csrf' => $tok,
    'status' => 'in_progress',
    'report_date' => $today,
    'reporter_name' => 'Smoke Tester',
    'dealer_id' => $dealerId,
    'item_id' => $itemId,
    'initial_report' => 'Tes laporan smoke phase 4 ' . microtime(true),
    'checking_notes' => '',
    'solution' => '',
    'started_at' => $started,
    'finished_at' => $finished,
    'assigned_user_id' => '',
];
$r = req($base, 'POST', '/tickets', $payload, $jarA);
check('store -> 302', $r['status'] === 302);
check('redirect to /tickets/{id}', is_string($r['location']) && preg_match('#^/tickets/\d+$#', $r['location']) === 1, "loc={$r['location']}");
$createdId = (int) (preg_match('#^/tickets/(\d+)$#', $r['location'] ?? '', $m) ? $m[1] : 0);
check('id captured', $createdId > 0);

// SHOW ticket.
echo "[T2] Show ticket\n";
$r = req($base, 'GET', '/tickets/' . $createdId, [], $jarA);
check('show 200', $r['status'] === 200);
check('shows ticket number', preg_match('/TKT-\d{6}-\d{4}/', $r['body']) === 1);
check('shows reporter', strpos($r['body'], 'Smoke Tester') !== false);
$ticketNumber = preg_match('/(TKT-\d{6}-\d{4})/', $r['body'], $m) ? $m[1] : '';

// LIST should include the ticket.
echo "[T3] List shows new ticket\n";
$r = req($base, 'GET', '/tickets', [], $jarA);
check('list 200', $r['status'] === 200);
check('list contains ticket number', $ticketNumber !== '' && strpos($r['body'], $ticketNumber) !== false);

// FILTER tests.
echo "[T4] Filters\n";
$month = (int) date('m');
$year = (int) date('Y');
$r = req($base, 'GET', "/tickets?month=$month&year=$year", [], $jarA);
check('filter month/year', strpos($r['body'], $ticketNumber) !== false);
$r = req($base, 'GET', "/tickets?status=in_progress", [], $jarA);
check('filter status=in_progress', strpos($r['body'], $ticketNumber) !== false);
$r = req($base, 'GET', "/tickets?dealer_id=$dealerId", [], $jarA);
check('filter dealer_id', strpos($r['body'], $ticketNumber) !== false);
$r = req($base, 'GET', "/tickets?item_id=$itemId", [], $jarA);
check('filter item_id', strpos($r['body'], $ticketNumber) !== false);
$r = req($base, 'GET', "/tickets?q=Smoke+Tester", [], $jarA);
check('filter q (search)', strpos($r['body'], $ticketNumber) !== false);
$r = req($base, 'GET', "/tickets?status=closed", [], $jarA);
check('filter excludes (status=closed empty)', strpos($r['body'], $ticketNumber) === false);

// EDIT ticket.
echo "[T5] Edit ticket\n";
$r = req($base, 'GET', "/tickets/$createdId/edit", [], $jarA);
$tokE = csrf($r['body']);
check('edit form 200 + csrf', $r['status'] === 200 && $tokE !== null);
$payloadEdit = $payload;
$payloadEdit['_csrf'] = $tokE;
$payloadEdit['reporter_name'] = 'Smoke Edited';
$payloadEdit['initial_report'] = 'Edited body';
$payloadEdit['status'] = 'pending';
$payloadEdit['finished_at'] = '';
$r = req($base, 'POST', "/tickets/$createdId", $payloadEdit, $jarA);
check('update -> 302', $r['status'] === 302 && $r['location'] === "/tickets/$createdId");
$r = req($base, 'GET', "/tickets/$createdId", [], $jarA);
check('updated reporter visible', strpos($r['body'], 'Smoke Edited') !== false);
check('status now pending', strpos($r['body'], 'badge-pending') !== false);

// CSRF rejection on update.
echo "[T6] CSRF rejection\n";
$payloadBad = $payloadEdit;
unset($payloadBad['_csrf']);
$r = req($base, 'POST', "/tickets/$createdId", $payloadBad, $jarA);
check('update without CSRF -> 419', $r['status'] === 419);

// CLOSE ticket.
echo "[T7] Close ticket\n";
$r = req($base, 'GET', "/tickets/$createdId", [], $jarA);
$tokC = csrf($r['body']);
$r = req($base, 'POST', "/tickets/$createdId/close", ['_csrf' => $tokC], $jarA);
check('close -> 302', $r['status'] === 302 && $r['location'] === "/tickets/$createdId");
$row = $pdo->prepare("SELECT status, lead_time_seconds, closed_by, closed_at, started_at, finished_at FROM tickets WHERE id = ?");
$row->execute([$createdId]);
$t = $row->fetch();
check('status closed in DB', ($t['status'] ?? '') === 'closed');
check('lead_time_seconds > 0', isset($t['lead_time_seconds']) && (int) $t['lead_time_seconds'] > 0,
    "lead_time={$t['lead_time_seconds']}");
check('closed_by set', !empty($t['closed_by']));
check('closed_at set', !empty($t['closed_at']));

// Validation: posting empty payload should re-render with errors.
echo "[T8] Validation\n";
$r = req($base, 'GET', '/tickets/create', [], $jarA);
$tokV = csrf($r['body']);
$bad = [
    '_csrf' => $tokV, 'status' => 'open', 'report_date' => '',
    'reporter_name' => '', 'dealer_id' => '', 'item_id' => '',
    'initial_report' => '', 'started_at' => '', 'finished_at' => '',
];
$r = req($base, 'POST', '/tickets', $bad, $jarA);
check('store invalid -> 302 /tickets/create', $r['status'] === 302 && $r['location'] === '/tickets/create');
$r = req($base, 'GET', '/tickets/create', [], $jarA);
check('errors flashed', strpos($r['body'], 'wajib diisi') !== false);

// ---------- VIEWER flow ----------

$jarV = newJar();
echo "\n=== Viewer flow (read-only) ===\n";
check('viewer login', loginAs($base, $jarV, $viewerEmail, $viewerPass));

$r = req($base, 'GET', '/tickets', [], $jarV);
check('viewer can list', $r['status'] === 200);
check('viewer cannot see Buat Ticket button', strpos($r['body'], 'Buat Ticket') === false);

$r = req($base, 'GET', '/tickets/create', [], $jarV);
check('viewer GET /tickets/create -> 403', $r['status'] === 403, "status={$r['status']}");

$r = req($base, 'GET', '/tickets/' . $createdId, [], $jarV);
check('viewer can see detail', $r['status'] === 200);

$r = req($base, 'GET', '/tickets/' . $createdId . '/edit', [], $jarV);
check('viewer GET edit -> 403', $r['status'] === 403);

// Viewer attempts POST update; needs CSRF anyway. RoleMiddleware should still 403.
$r = req($base, 'GET', '/login', [], $jarV); // refresh CSRF (any GET sets it)
$tokV = csrf($r['body']);
$r = req($base, 'GET', '/tickets', [], $jarV); // get any rendered csrf token (logout form)
$tokV = csrf($r['body']);
$r = req($base, 'POST', "/tickets/$createdId/close", ['_csrf' => $tokV], $jarV);
check('viewer POST close -> 403', $r['status'] === 403, "status={$r['status']}");

// Health still public.
$r = req($base, 'GET', '/health', [], newJar());
$json = json_decode($r['body'], true);
check('health public + db ok', $r['status'] === 200 && ($json['db'] ?? '') === 'ok');

echo "\n================================================\n";
if ($failures === 0) { echo "ALL PASSED\n"; exit(0); }
echo "FAILED: $failures check(s)\n";
exit(1);
