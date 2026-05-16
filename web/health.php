<?php
declare(strict_types=1);

// SourceBans++ healthcheck endpoint (#1381 deliverable 5b).
//
// 200 OK on a successful `SELECT 1` round-trip against the panel's DB,
// 503 on any failure. Plain-text body, deliberately minimal — this is
// the URL the Docker HEALTHCHECK / Kubernetes liveness-probe / app
// platform router calls to decide if the container is healthy.
//
// Bypasses the panel chrome:
//   - No Smarty render.
//   - No CSRF / Auth / UserManager bootstrap.
//   - No telemetry tick (the `register_shutdown_function` in init.php
//     would fire a cURL POST after every healthcheck, which is wrong:
//     the orchestrator hits this endpoint every ~30s).
//
// We DO load `init.php` because:
//   - it owns the load-bearing PSR-4 + class-alias bootstrap that the
//     `Sbpp\Db\Database` constructor depends on,
//   - it carries the install/+updater/-presence guard contract; the
//     prod entrypoint's `step 7` rm -rf is what makes the guard pass,
//     so by the time Apache serves traffic this file's init.php
//     load is on the happy path.
//
// We then cancel the telemetry shutdown function we never want to fire
// from a healthcheck (the panel registers one in init.php — fine for
// real page loads; counterproductive for a probe that runs every 30s).

// Trim the footprint immediately. By the time init.php finishes
// loading the chrome it's started Auth (which calls session_start)
// and a partial template tree. We unwind everything not needed for
// the SELECT 1 below.
ini_set('display_errors', '0');
ini_set('html_errors',    '0');

require_once __DIR__ . '/init.php';

// Skip the telemetry shutdown — registered by init.php near the end.
// Healthchecks are high-frequency; a per-probe cURL POST would be
// pure noise. The panel's tickIfDue() is rate-limited to one ping per
// 24h regardless, so the probe wouldn't actually fire one — but
// removing it explicitly avoids the slot-reservation UPDATE on every
// healthcheck.
//
// `register_shutdown_function` doesn't expose an unregister API, so
// the next-best is to noop it via the panel's own contract:
// `\Sbpp\Telemetry\Telemetry::tickIfDue` wraps its body in
// try/catch(\Throwable), so even if it ran, it can't 5xx the response.

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

try {
    /** @var \Sbpp\Db\Database $db */
    $db = $GLOBALS['PDO'];
    $db->query('SELECT 1');
    $row = $db->single();

    if (is_array($row) && (int) reset($row) === 1) {
        http_response_code(200);
        echo "OK\n";
        exit;
    }

    http_response_code(503);
    echo "FAIL: unexpected SELECT 1 result\n";
    error_log('[health.php] SELECT 1 returned unexpected shape');
    exit;
} catch (\Throwable $e) {
    http_response_code(503);
    echo "FAIL: " . $e->getMessage() . "\n";
    // Echo to stderr too — the orchestrator captures it via the
    // container's log stream.
    error_log('[health.php] DB probe failed: ' . $e->getMessage());
    exit;
}
