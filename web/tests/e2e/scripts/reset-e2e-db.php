<?php
/**
 * E2E DB seeder.
 *
 * Runs inside the web container, called from
 * `web/tests/e2e/fixtures/db.ts` (host-side via `docker compose exec`,
 * in-container via direct `php` when running under `./sbpp.sh e2e`).
 *
 * Reuses the same `Sbpp\Tests\Fixture` PHPUnit uses, but pointed at
 * `sourcebans_e2e` so it never collides with the dev DB (`sourcebans`)
 * or the PHPUnit DB (`sourcebans_test`). Same struc.sql + data.sql,
 * same seeded admin (admin/admin), same `:prefix_` rewrite — just a
 * different schema name.
 *
 * Usage (from inside the container):
 *
 *   php reset-e2e-db.php             # Fixture::install()  (drop + recreate)
 *   php reset-e2e-db.php --truncate  # Fixture::reset()    (truncate + re-seed)
 *
 * Env vars consumed (defaulted in tests/bootstrap.php to the same
 * docker-compose values, with DB_NAME swapped for `sourcebans_e2e`):
 *
 *   DB_HOST     default 'db'
 *   DB_PORT     default 3306
 *   DB_USER     default 'sourcebans'
 *   DB_PASS     default 'sourcebans'
 *   DB_PREFIX   default 'sb'
 *   DB_CHARSET  default 'utf8mb4'
 *   DB_NAME     default 'sourcebans_e2e' (intentionally NOT 'sourcebans_test')
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "reset-e2e-db.php must run on the CLI.\n");
    exit(2);
}

// `tests/bootstrap.php` reads `DB_NAME` via `getenv()` and falls back
// to `sourcebans_test`. Pin the e2e DB before bootstrap loads so the
// fall-back path can never resurrect the PHPUnit schema by accident.
if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to seed e2e fixture against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

$truncate = in_array('--truncate', array_slice($argv, 1), true);

try {
    if ($truncate) {
        // truncateOnly() (added in #1124) skips the DROP+CREATE the
        // PHPUnit-shaped reset() goes through. The shim runs in a
        // fresh PHP process every time, so reset()'s install() call
        // would always fire DROP+CREATE and race with parallel
        // Playwright workers on the same DB.
        \Sbpp\Tests\Fixture::truncateOnly();
        fwrite(STDOUT, "e2e DB truncated on " . DB_NAME . "\n");
    } else {
        \Sbpp\Tests\Fixture::install();
        fwrite(STDOUT, "e2e DB installed on " . DB_NAME . "\n");
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "reset-e2e-db.php failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
