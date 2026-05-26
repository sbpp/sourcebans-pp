<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

/**
 * E2E throttle-cache cleaner for the `system.test_email` handler (#1455).
 *
 * `api_system_test_email` rate-limits at 10s per install via a single
 * cache file `SB_CACHE/test-email-throttle`. The throttle is global
 * (not per-recipient) — the threat model the rate limit defends is
 * "operator-controlled SMTP relay abuse", which doesn't care about
 * the recipient axis. Specs that exercise the happy path under
 * parallel Playwright project profiles (chromium + mobile-chromium)
 * end up colliding on the same throttle slot, so they need an
 * explicit reset between tests to stay deterministic.
 *
 * Idempotent: silently no-ops when the file isn't present.
 *
 * Usage (inside the web container):
 *
 *   php clear-test-email-throttle-e2e.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "clear-test-email-throttle-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

// Production / phpunit DBs share the panel's cache directory in dev
// stacks (SB_CACHE is derived from the panel root, not the schema
// name), so unlinking the throttle file would also wipe it for any
// concurrent dev / phpunit session against the same install. Mirror
// the refuse-guard shape used by reset-e2e-db.php so a fat-fingered
// `DB_NAME=sourcebans php …` invocation aborts loudly instead of
// silently scrubbing the wrong stack's cache.
if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to clear test-email throttle against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

$cacheFile = SB_CACHE . 'test-email-throttle';
@unlink($cacheFile);
fwrite(STDOUT, "cleared $cacheFile\n");
