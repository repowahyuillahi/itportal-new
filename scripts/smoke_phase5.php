<?php

declare(strict_types=1);

/**
 * Phase 5 smoke test for /dealers and /items master data.
 *
 * Requires migrations + admin user + seeded items. Will create a
 * `viewer@itportal.local` and `it@itportal.local` user if missing.
 */

$base = $argv[1] ?? 'http://127.0.0.1:8769';
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

$adminId = (int) ($pdo->query("SELECT id FROM users WHERE email='admin@itportal.local'")->fetchColumn() ?: 0);
check('admin user exists', $adminId > 0, 'run scripts/create_admin.php first');

// Ensure helper users for role tests.
$ensureUser = static function (PDO $pdo, string $email, string $name, string $role, string $password): int {
    $id = (int) ($pdo->prepare('SELECT id FROM users WHERE email = ?')
        ->execute([$email]) ? 0 : 0);
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

$viewerId = $ensureUser($pdo, 'viewer@itportal.local', 'Viewer Tester',  'viewer',   'viewer123');
$itId     = $ensureUser($pdo, 'it@itportal.local',     'IT Staff Tester','it_staff', 'itstaff123');
check('viewer user ready', $viewerId > 0);
check('it_staff user ready', $itId > 0);

// ---------- ADMIN dealer flow ----------

$jarA = newJar();
echo "\n=== Admin DEALER flow ===\n";
check('admin login', loginAs($base, $jarA, 'admin@itportal.local', 'secret123'));

$r = req($base, 'GET', '/dealers', [], $jarA);
check('dealers list 200', $r['status'] === 200);
check('admin sees Buat Dealer', strpos($r['body'], 'Buat Dealer') !== false);

$r = req($base, 'GET', '/dealers/create', [], $jarA);
$tok = csrf($r['body']);
check('dealer form CSRF', $tok !== null);

$dealerCode = 'TST-D-' . substr((string) microtime(true), -6);
$dealerName = 'Smoke Dealer ' . substr((string) microtime(true), -4);
$r = req($base, 'POST', '/dealers', [
    '_csrf' => $tok, 'code' => $dealerCode, 'name' => $dealerName,
    'area' => 'Jakarta', 'address' => 'Jl. Test 1',
    'pic_name' => 'Pak PIC', 'pic_phone' => '0812', 'status' => 'active',
], $jarA);
check('dealer store -> 302 /dealers', $r['status'] === 302 && $r['location'] === '/dealers');

// Find inserted id.
$stmt = $pdo->prepare('SELECT id FROM dealers WHERE code = ?');
$stmt->execute([$dealerCode]);
$dealerId = (int) ($stmt->fetchColumn() ?: 0);
check('dealer row exists', $dealerId > 0);

// Duplicate code is rejected.
$r = req($base, 'GET', '/dealers/create', [], $jarA);
$tok2 = csrf($r['body']);
$r = req($base, 'POST', '/dealers', [
    '_csrf' => $tok2, 'code' => $dealerCode, 'name' => 'Dup Test', 'status' => 'active',
], $jarA);
check('duplicate code -> 302 /dealers/create', $r['status'] === 302 && $r['location'] === '/dealers/create');
$r = req($base, 'GET', '/dealers/create', [], $jarA);
check('duplicate code error flashed', strpos($r['body'], 'sudah dipakai') !== false);

// Edit dealer.
$r = req($base, 'GET', "/dealers/$dealerId/edit", [], $jarA);
$tokE = csrf($r['body']);
check('dealer edit form 200', $r['status'] === 200 && $tokE !== null);
$r = req($base, 'POST', "/dealers/$dealerId", [
    '_csrf' => $tokE, 'code' => $dealerCode, 'name' => $dealerName . ' EDIT',
    'area' => 'Bandung', 'status' => 'active',
], $jarA);
check('dealer update -> 302 /dealers', $r['status'] === 302 && $r['location'] === '/dealers');
$r = req($base, 'GET', '/dealers?q=' . urlencode('EDIT'), [], $jarA);
check('updated dealer name visible', strpos($r['body'], $dealerName . ' EDIT') !== false);

// Toggle status.
$r = req($base, 'GET', '/dealers', [], $jarA);
$tokS = csrf($r['body']);
$r = req($base, 'POST', "/dealers/$dealerId/status", ['_csrf' => $tokS], $jarA);
check('dealer toggle -> 302', $r['status'] === 302 && $r['location'] === '/dealers');
$row = $pdo->prepare('SELECT status FROM dealers WHERE id = ?');
$row->execute([$dealerId]);
check('dealer now inactive', $row->fetchColumn() === 'inactive');

// CSRF rejection on dealer update.
$r = req($base, 'POST', "/dealers/$dealerId", ['name' => 'No CSRF'], $jarA);
check('dealer update without CSRF -> 419', $r['status'] === 419);

// ---------- ADMIN item flow ----------

echo "\n=== Admin ITEM flow ===\n";
$r = req($base, 'GET', '/items/create', [], $jarA);
$tok = csrf($r['body']);
check('item form CSRF', $tok !== null);

$itemName = 'Smoke Item ' . substr((string) microtime(true), -4);
$r = req($base, 'POST', '/items', [
    '_csrf' => $tok, 'name' => $itemName, 'slug' => '',
    'description' => 'desc', 'status' => 'active', 'sort_order' => '',
], $jarA);
check('item store -> 302 /items', $r['status'] === 302 && $r['location'] === '/items');

$stmt = $pdo->prepare('SELECT id, slug, status FROM items WHERE name = ?');
$stmt->execute([$itemName]);
$itemRow = $stmt->fetch();
check('item row exists', is_array($itemRow));
$itemId = (int) ($itemRow['id'] ?? 0);
$autoSlug = (string) ($itemRow['slug'] ?? '');
check('slug auto-generated', $autoSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $autoSlug) === 1, "slug=$autoSlug");

// Duplicate slug is rejected.
$r = req($base, 'GET', '/items/create', [], $jarA);
$tok2 = csrf($r['body']);
$r = req($base, 'POST', '/items', [
    '_csrf' => $tok2, 'name' => 'Other Name', 'slug' => $autoSlug, 'status' => 'active',
], $jarA);
check('duplicate slug -> 302 /items/create', $r['status'] === 302 && $r['location'] === '/items/create');
$r = req($base, 'GET', '/items/create', [], $jarA);
check('duplicate slug error flashed', strpos($r['body'], 'sudah dipakai') !== false);

// Bad slug format rejected.
$r = req($base, 'GET', '/items/create', [], $jarA);
$tok3 = csrf($r['body']);
$r = req($base, 'POST', '/items', [
    '_csrf' => $tok3, 'name' => 'Bad Slug', 'slug' => 'BAD SLUG WITH SPACE!', 'status' => 'active',
], $jarA);
check('invalid slug -> 302', $r['status'] === 302 && $r['location'] === '/items/create');
$r = req($base, 'GET', '/items/create', [], $jarA);
check('invalid slug error', strpos($r['body'], 'huruf kecil') !== false);

// Edit item.
$r = req($base, 'GET', "/items/$itemId/edit", [], $jarA);
$tokE = csrf($r['body']);
$r = req($base, 'POST', "/items/$itemId", [
    '_csrf' => $tokE, 'name' => $itemName . ' EDIT',
    'slug' => $autoSlug, 'status' => 'active', 'sort_order' => '5',
], $jarA);
check('item update -> 302 /items', $r['status'] === 302 && $r['location'] === '/items');

// Toggle item status.
$r = req($base, 'GET', '/items', [], $jarA);
$tokS = csrf($r['body']);
$r = req($base, 'POST', "/items/$itemId/status", ['_csrf' => $tokS], $jarA);
check('item toggle -> 302', $r['status'] === 302);
$row = $pdo->prepare('SELECT status FROM items WHERE id = ?');
$row->execute([$itemId]);
check('item now inactive', $row->fetchColumn() === 'inactive');

// ---------- TICKET integration: inactive dealer/item not in selectors ----------

echo "\n=== Ticket form integration ===\n";
$r = req($base, 'GET', '/tickets/create', [], $jarA);
check('ticket create 200', $r['status'] === 200);
// Inactive dealer/item must not appear in form. Match by the (unique) name we
// inserted, since IDs may collide across tables when used as <option value=...>.
check('inactive dealer NOT in ticket form',
    strpos($r['body'], $dealerName . ' EDIT') === false);
check('inactive item NOT in ticket form',
    strpos($r['body'], $itemName . ' EDIT') === false);

// Historical ticket safety: create a ticket while dealer+item are active,
// then deactivate them, and verify the ticket detail page still loads with
// the dealer/item names visible.
// Reactivate temporarily to insert.
$pdo->prepare('UPDATE dealers SET status = "active" WHERE id = ?')->execute([$dealerId]);
$pdo->prepare('UPDATE items   SET status = "active" WHERE id = ?')->execute([$itemId]);

$r = req($base, 'GET', '/tickets/create', [], $jarA);
$tokT = csrf($r['body']);
$started  = date('Y-m-d\TH:i', strtotime('-30 min'));
$finished = date('Y-m-d\TH:i');
$r = req($base, 'POST', '/tickets', [
    '_csrf' => $tokT,
    'status' => 'in_progress',
    'report_date' => date('Y-m-d'),
    'reporter_name' => 'Phase5 historical',
    'dealer_id' => $dealerId,
    'item_id' => $itemId,
    'initial_report' => 'historical ticket using to-be-inactive master',
    'started_at' => $started,
    'finished_at' => $finished,
], $jarA);
check('historical ticket create -> 302', $r['status'] === 302
    && is_string($r['location']) && preg_match('#^/tickets/\d+$#', $r['location']) === 1);
$histTicketId = (int) (preg_match('#^/tickets/(\d+)$#', $r['location'] ?? '', $m) ? $m[1] : 0);

// Re-deactivate dealer & item.
$pdo->prepare('UPDATE dealers SET status = "inactive" WHERE id = ?')->execute([$dealerId]);
$pdo->prepare('UPDATE items   SET status = "inactive" WHERE id = ?')->execute([$itemId]);

$r = req($base, 'GET', '/tickets/' . $histTicketId, [], $jarA);
check('historical ticket detail 200', $r['status'] === 200);
check('historical detail shows inactive dealer name',
    strpos($r['body'], $dealerName . ' EDIT') !== false);
check('historical detail shows inactive item name',
    strpos($r['body'], $itemName . ' EDIT') !== false);

$r = req($base, 'GET', '/tickets', [], $jarA);
check('tickets list still 200 with inactive master', $r['status'] === 200);

// ---------- ROLE tests ----------

echo "\n=== Role tests ===\n";

// it_staff: dealers/items list ok, mutating 403.
$jarIT = newJar();
check('it_staff login', loginAs($base, $jarIT, 'it@itportal.local', 'itstaff123'));
$r = req($base, 'GET', '/dealers', [], $jarIT);
check('it_staff GET /dealers 200', $r['status'] === 200);
check('it_staff cannot see Buat Dealer', strpos($r['body'], 'Buat Dealer') === false);
$r = req($base, 'GET', '/dealers/create', [], $jarIT);
check('it_staff GET /dealers/create -> 403', $r['status'] === 403);
$r = req($base, 'POST', "/dealers/$dealerId/status", [], $jarIT);
check('it_staff toggle dealer -> 403/419', in_array($r['status'], [403, 419], true));
$r = req($base, 'GET', '/items', [], $jarIT);
check('it_staff GET /items 200', $r['status'] === 200);
$r = req($base, 'GET', '/items/create', [], $jarIT);
check('it_staff GET /items/create -> 403', $r['status'] === 403);

// viewer: same.
$jarV = newJar();
check('viewer login', loginAs($base, $jarV, 'viewer@itportal.local', 'viewer123'));
$r = req($base, 'GET', '/dealers', [], $jarV);
check('viewer GET /dealers 200', $r['status'] === 200);
$r = req($base, 'GET', '/dealers/create', [], $jarV);
check('viewer GET /dealers/create -> 403', $r['status'] === 403);
$r = req($base, 'POST', "/items/$itemId/status", [], $jarV);
check('viewer toggle item -> 403/419', in_array($r['status'], [403, 419], true));

// Anon
$jarAnon = newJar();
$r = req($base, 'GET', '/dealers', [], $jarAnon);
check('anon /dealers -> /login', $r['status'] === 302 && $r['location'] === '/login');
$r = req($base, 'GET', '/items', [], $jarAnon);
check('anon /items -> /login', $r['status'] === 302 && $r['location'] === '/login');

// /health public + db ok.
$r = req($base, 'GET', '/health', [], newJar());
$json = json_decode($r['body'], true);
check('/health db: ok', $r['status'] === 200 && ($json['db'] ?? '') === 'ok');

// Audit spot check.
echo "\n=== Audit spot check ===\n";
$rows = $pdo->query("SELECT action, COUNT(*) c FROM audit_logs
                     WHERE action LIKE 'dealer.%' OR action LIKE 'item.%' GROUP BY action")->fetchAll();
foreach ($rows as $r) { echo "    {$r['action']}: {$r['c']}\n"; }
$actions = array_column($rows, 'action');
check('dealer.create logged', in_array('dealer.create', $actions, true));
check('dealer.update logged', in_array('dealer.update', $actions, true));
check('dealer.status logged', in_array('dealer.status', $actions, true));
check('item.create logged',   in_array('item.create',   $actions, true));
check('item.update logged',   in_array('item.update',   $actions, true));
check('item.status logged',   in_array('item.status',   $actions, true));

echo "\n================================================\n";
if ($failures === 0) { echo "ALL PASSED\n"; exit(0); }
echo "FAILED: $failures check(s)\n";
exit(1);
