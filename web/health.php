<?php
declare(strict_types=1);

// SourceBans++ healthcheck endpoint (#1381 deliverable 5b).
//
// 200 OK on a successful `SELECT 1` against the panel's DB, 503
// on any failure. What Docker HEALTHCHECK / k8s liveness probes /
// app-platform routers hit every ~30s.
//
// Two contracts this endpoint MUST honour (#1381 review):
//
//   * CRIT-3: NEVER echo `$e->getMessage()`. PDO carries SQLSTATE
//     + DB host + internal IP + username + auth mechanism in its
//     exception text — leaking that to an unauth caller is a
//     reconnaissance gift. Full detail goes to `error_log` for
//     the operator instead.
//
//   * MED-4: `SBPP_SKIP_TELEMETRY` defined BEFORE init.php so the
//     `Telemetry::tickIfDue` shutdown function early-returns
//     without the per-probe slot-reservation UPDATE on
//     `:prefix_settings.last_ping`.
//
// init.php is still loaded for Sbpp\Db\Database; the entrypoint's
// step-7 strip puts the runtime-guard check on the happy path.

define('SBPP_SKIP_TELEMETRY', true);
ini_set('display_errors', '0');
ini_set('html_errors',    '0');

require_once __DIR__ . '/init.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

try {
    $GLOBALS['PDO']->query('SELECT 1');
    $row = $GLOBALS['PDO']->single();
    if (is_array($row) && (int) reset($row) === 1) {
        http_response_code(200);
        echo "OK\n";
        exit;
    }
    error_log('[health.php] SELECT 1 returned unexpected shape');
} catch (\Throwable $e) {
    error_log('[health.php] DB probe failed: ' . $e->getMessage());
}
http_response_code(503);
echo "FAIL\n";
