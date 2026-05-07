<?php
/**
 * Dev DB seeder CLI driver. Wired into `./sbpp.sh db-seed`.
 *
 * Runs inside the web container, populates `sourcebans` (the dev DB) with
 * a deterministic, realistic synthetic dataset. Idempotent: every run
 * truncates the synthesizer-owned tables first and re-inserts. Calls
 * {@see \Sbpp\Tests\Synthesizer::run()}; the heavy lifting lives there.
 *
 * Usage (from inside the container):
 *
 *   php seed-dev-db.php                          # default scale, default seed
 *   php seed-dev-db.php --scale=small            # ~30 bans, fast iteration
 *   php seed-dev-db.php --scale=large            # ~2000 bans, pagination/perf
 *   php seed-dev-db.php --seed=42                # alternate RNG seed
 *   php seed-dev-db.php --scale=small --seed=42  # both
 *
 * Env vars consumed (defaulted in tests/bootstrap.php; sbpp.sh wires
 * the docker-compose values):
 *
 *   DB_HOST     default 'db'
 *   DB_PORT     default 3306
 *   DB_USER     default 'sourcebans'
 *   DB_PASS     default 'sourcebans'
 *   DB_PREFIX   default 'sb'
 *   DB_CHARSET  default 'utf8mb4'
 *   DB_NAME     **MUST be 'sourcebans'** — every other value is refused
 *
 * Refusal guard: `tests/bootstrap.php` defaults `DB_NAME` to
 * 'sourcebans_test' when the env var is unset. We pin DB_NAME to
 * 'sourcebans' before bootstrap loads, then refuse anything else
 * outright. This is the strictest interpretation of the issue's
 * "dev-only" constraint — the seeder will not touch:
 *
 *   - `sourcebans_test` (PHPUnit's DB)
 *   - `sourcebans_e2e`  (Playwright's DB)
 *   - any production DB the operator might rename to
 *
 * Production users with a default `sourcebans` install would, in
 * principle, also pass the guard. The defence-in-depth there is that
 * (a) production installs don't typically have CLI access set up to
 * the panel container, (b) running the script blows away every row
 * the script knows about — a destructive action no production install
 * could accidentally trigger without an explicit `php` invocation.
 * The Synthesizer also re-checks `DB_NAME` at `run()` entry as a
 * belt-and-suspenders guard.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-dev-db.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans');
    $_ENV['DB_NAME']    = 'sourcebans';
    $_SERVER['DB_NAME'] = 'sourcebans';
}

if (getenv('DB_NAME') !== 'sourcebans') {
    fwrite(STDERR, "refusing to seed dev DB against DB_NAME=" . getenv('DB_NAME')
        . ": only 'sourcebans' is allowed (the dev panel's database).\n");
    fwrite(STDERR, "If you want to seed a different DB, override DB_NAME explicitly to 'sourcebans'\n");
    fwrite(STDERR, "and re-check what you're doing — every synth-owned table will be truncated.\n");
    exit(2);
}

require __DIR__ . '/../bootstrap.php';

// `tests/bootstrap.php` only auto-loads `Fixture.php`; the Synthesizer
// is dev-only and lives under `Sbpp\Tests\` next to it. Pull it in
// explicitly so we don't have to amend the PHPUnit bootstrap (which is
// production code path-wise).
require __DIR__ . '/../Synthesizer.php';

[$scale, $seed] = parse_args(array_slice($argv, 1));

try {
    $start  = microtime(true);
    $counts = \Sbpp\Tests\Synthesizer::run($scale, $seed);
    $elapsed = microtime(true) - $start;

    fwrite(STDOUT, sprintf(
        "Seeded `%s` on %s with scale=%s seed=%d in %.2fs.\n",
        DB_NAME,
        DB_HOST . ':' . DB_PORT,
        $scale,
        $seed,
        $elapsed
    ));
    fwrite(STDOUT, "Inserted rows:\n");
    foreach ($counts as $table => $n) {
        fwrite(STDOUT, sprintf("  %-22s %6d\n", $table, $n));
    }
    fwrite(STDOUT, "\n");
    fwrite(STDOUT, "Login at the panel as admin / admin to see the seeded data.\n");
    // sb_login_tokens is one of the truncated tables, so any open browser
    // session against the dev panel is invalidated and the next request
    // will bounce back to the login form. Cheap one-line hint so the dev
    // doesn't second-guess "did the panel break?" on a re-seed.
    fwrite(STDOUT, "Re-login required: existing browser sessions were invalidated by the truncate.\n");
} catch (\Throwable $e) {
    fwrite(STDERR, "seed-dev-db.php failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

/**
 * Parse `--scale=<tier>` and `--seed=<int>` flags. Order-independent;
 * unknown flags are an error so a typo doesn't silently fall through
 * to the default. The defaults match the issue's "default scale ~200/100/8/8/4"
 * and we pin the seed to {@see \Sbpp\Tests\Synthesizer::DEFAULT_SEED}
 * for reproducibility across invocations.
 *
 * @param list<string> $args
 * @return array{string, int}
 */
function parse_args(array $args): array
{
    $scale = 'medium';
    $seed  = \Sbpp\Tests\Synthesizer::DEFAULT_SEED;

    foreach ($args as $arg) {
        if (preg_match('/^--scale=(.+)$/', $arg, $m) === 1) {
            $scale = $m[1];
            if (!isset(\Sbpp\Tests\Synthesizer::SCALES[$scale])) {
                fwrite(STDERR, "unknown --scale '$scale'; expected one of: "
                    . implode(', ', array_keys(\Sbpp\Tests\Synthesizer::SCALES)) . "\n");
                exit(2);
            }
            continue;
        }
        if (preg_match('/^--seed=(-?\d+)$/', $arg, $m) === 1) {
            $seed = (int) $m[1];
            continue;
        }
        if ($arg === '-h' || $arg === '--help') {
            fwrite(STDOUT, "Usage: php seed-dev-db.php [--scale=small|medium|large] [--seed=<int>]\n");
            exit(0);
        }
        fwrite(STDERR, "unknown argument '$arg'. Try --help.\n");
        exit(2);
    }

    return [$scale, $seed];
}
