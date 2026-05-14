<?php

declare(strict_types=1);

/**
 * Phase 3 smoke test. Uses cURL with a shared cookie jar to simulate a
 * real browser session against the built-in PHP server.
 *
 * Usage:
 *   php scripts/smoke_phase3.php [base_url]
 */

$base = $argv[1] ?? 'http://127.0.0.1:8767';
$jar = tempnam(sys_get_temp_dir(), 'itp-cookies-');
register_shutdown_function(static function () use ($jar) { @unlink($jar); });

$failures = 0;

function req(string $base, string $method, string $path, array $form = [], string $jar = '', array $headers = []): array
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
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        return ['status' => 0, 'headers' => [], 'body' => '', 'error' => curl_error($ch)];
    }
    $info = curl_getinfo($ch);
    $hsize = $info['header_size'];
    $hdrText = substr((string) $raw, 0, $hsize);
    $body = substr((string) $raw, $hsize);
    $location = null;
    foreach (explode("\r\n", $hdrText) as $line) {
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, 9));
        }
    }
    curl_close($ch);
    return [
        'status' => (int) $info['http_code'],
        'location' => $location,
        'body' => $body,
    ];
}

function csrf(string $html): ?string
{
    if (preg_match('/name="_csrf"\s+value="([0-9a-f]+)"/', $html, $m)) {
        return $m[1];
    }
    return null;
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    $mark = $ok ? '[ OK ]' : '[FAIL]';
    if (!$ok) {
        $failures++;
    }
    echo "  $mark $name" . ($detail !== '' ? "  -- $detail" : '') . "\n";
}

echo "Phase 3 smoke tests against $base\n";
echo "================================================\n";

// T1: /health public + db ok
echo "[T1] GET /health (public)\n";
$r = req($base, 'GET', '/health', [], $jar);
$json = json_decode($r['body'], true);
check('200', $r['status'] === 200, "got {$r['status']}");
check('db ok', is_array($json) && ($json['db'] ?? '') === 'ok', "db=" . ($json['db'] ?? '?'));

// T2: GET /login as guest
echo "[T2] GET /login (guest)\n";
$r = req($base, 'GET', '/login', [], $jar);
check('200', $r['status'] === 200);
$tok = csrf($r['body']);
check('csrf token present', $tok !== null);

// T3: GET /dashboard without auth -> 302 /login
echo "[T3] GET /dashboard (unauth)\n";
$r = req($base, 'GET', '/dashboard', [], $jar);
check('302', $r['status'] === 302);
check('-> /login', $r['location'] === '/login', "location={$r['location']}");

// T4: POST /login wrong password
echo "[T4] POST /login wrong password\n";
$r = req($base, 'POST', '/login', ['_csrf' => $tok, 'email' => 'admin@itportal.local', 'password' => 'WRONG'], $jar);
check('302', $r['status'] === 302);
check('-> /login', $r['location'] === '/login');
$r2 = req($base, 'GET', '/login', [], $jar);
$hasError = (bool) preg_match('/alert-error[^>]*>([^<]+)/', $r2['body'], $m);
check('error flash shown', $hasError, $m[1] ?? '');

// T5: POST /login without CSRF -> 419
echo "[T5] POST /login without CSRF\n";
$r = req($base, 'POST', '/login', ['email' => 'admin@itportal.local', 'password' => 'secret123'], $jar);
check('419', $r['status'] === 419, "got {$r['status']}");

// Get fresh CSRF
$r = req($base, 'GET', '/login', [], $jar);
$tok = csrf($r['body']);

// T6: POST /login inactive user
echo "[T6] POST /login inactive user\n";
$r = req($base, 'POST', '/login', ['_csrf' => $tok, 'email' => 'inactive@itportal.local', 'password' => 'inactive123'], $jar);
check('302', $r['status'] === 302);
check('-> /login', $r['location'] === '/login');
$r2 = req($base, 'GET', '/login', [], $jar);
$hasInactive = (bool) preg_match('/(tidak aktif|Akun tidak aktif)/i', $r2['body']);
check('inactive flash shown', $hasInactive);

// T7: POST /login correct -> /dashboard
$tok = csrf($r2['body']);
echo "[T7] POST /login correct\n";
$r = req($base, 'POST', '/login', ['_csrf' => $tok, 'email' => 'admin@itportal.local', 'password' => 'secret123'], $jar);
check('302', $r['status'] === 302);
check('-> /dashboard', $r['location'] === '/dashboard', "location={$r['location']}");

// T8: GET /dashboard authed
echo "[T8] GET /dashboard (authed)\n";
$r = req($base, 'GET', '/dashboard', [], $jar);
check('200', $r['status'] === 200);
check('shows admin role', str_contains($r['body'], '<code>admin</code>'));
$dashTok = csrf($r['body']);
check('logout csrf present', $dashTok !== null);

// T9: GET /login while authed -> /dashboard
echo "[T9] GET /login (authed)\n";
$r = req($base, 'GET', '/login', [], $jar);
check('302', $r['status'] === 302);
check('-> /dashboard', $r['location'] === '/dashboard');

// T10: POST /logout (with CSRF)
echo "[T10] POST /logout\n";
$r = req($base, 'POST', '/logout', ['_csrf' => $dashTok], $jar);
check('302', $r['status'] === 302);
check('-> /login', $r['location'] === '/login');

// T11: GET /dashboard after logout -> /login
echo "[T11] GET /dashboard (post-logout)\n";
$r = req($base, 'GET', '/dashboard', [], $jar);
check('302', $r['status'] === 302);
check('-> /login', $r['location'] === '/login');

// T12: /health still public + ok
echo "[T12] GET /health still public\n";
$r = req($base, 'GET', '/health', [], $jar);
$json = json_decode($r['body'], true);
check('200 + db ok', $r['status'] === 200 && ($json['db'] ?? '') === 'ok');

// Audit log spot check
echo "[T13] Audit log entries\n";
require __DIR__ . '/../bootstrap.php';
$pdo = App\Core\Database::pdo();
$rows = $pdo->query("SELECT action, COUNT(*) c FROM audit_logs GROUP BY action")->fetchAll();
foreach ($rows as $row) {
    echo "    {$row['action']}: {$row['c']}\n";
}
$actions = array_column($rows, 'action');
check('login.success logged', in_array('login.success', $actions, true));
check('login.failed logged',  in_array('login.failed',  $actions, true));
check('logout logged',        in_array('logout',         $actions, true));

echo "================================================\n";
if ($failures === 0) {
    echo "ALL PASSED\n";
    exit(0);
}
echo "FAILED: $failures check(s)\n";
exit(1);
