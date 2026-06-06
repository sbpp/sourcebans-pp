<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1498: `web/updater/data/801.php` shipped
 * `ALTER TABLE ... ADD IF NOT EXISTS` — a MariaDB-only DDL extension that
 * stock MySQL (and MySQL-compatible engines like Percona Server) reject
 * with `SQLSTATE[42000] 1064` mid-upgrade. The dev stack + CI both run
 * MariaDB (which accepts the syntax), so the runtime idempotency test in
 * `Updater801LockoutColumnsTest` AND PHPStan's dba gate both passed it
 * through; the break only surfaced on self-hosters running MySQL / Percona,
 * which the docs support as first-class engines (prerequisites.mdx:
 * "MySQL >= 5.6 ... 8.0+ is fine and recommended").
 *
 * A runtime test can't catch this — it would need a live MySQL to fail on,
 * and the suite runs against MariaDB. This is a STATIC source-scan guard:
 * no v2-era migration (version >= 800) may use the `ADD ... IF NOT EXISTS`
 * conditional-DDL form. The portable shape is an information_schema
 * existence probe + a plain `ADD COLUMN` (see 801.php for the reference).
 *
 * Scope (focused, per #1498)
 * --------------------------
 * Only migrations with version >= 800 (the 8xx v2 block, 801-810 today)
 * are scanned. Two reasons:
 *
 *   - Every supported upgrade path the issue names (v2 rc5 -> rc6,
 *     v1.8.x -> v2) starts at `config.version = 705`, so the updater only
 *     runs 801+. 801 is the first migration past 705 and the actual
 *     blocker. Future migrations are always numbered above the current
 *     max (811+), so a `>= 800` floor guards every migration that will
 *     ever be added going forward.
 *   - Ten pre-800 migrations carry the same MariaDB-only syntax
 *     (1, 112, 150, 153, 160, 241, 291, 295, 351, 355). They only run for
 *     ancient (pre-356, SB 1.5.x-era) installs upgrading straight to v2 on
 *     MySQL / Percona — a real but rarer path, and a couple have quirks
 *     (295 is a compound `ADD ..., ADD ...`; 355 has a hardcoded `sb_mods`
 *     prefix). They're a tracked follow-up, deliberately out of scope here
 *     so this fix stays small and low-risk.
 *
 * `CREATE TABLE IF NOT EXISTS` (805.php, 700.php, 475.php, ...) is VALID on
 * every engine and is intentionally NOT matched — the regex is anchored on
 * `ADD ... IF NOT EXISTS`, the column / index form MySQL lacks.
 *
 * Mirrors `DeadJsCallSitesTest`'s pure-file-scan shape: extends
 * `PHPUnit\Framework\TestCase` (no DB / Smarty bring-up), strips PHP
 * comments via `php_strip_whitespace()` so a migration's own docblock
 * mentioning the forbidden syntax (801.php does) doesn't false-fire.
 */
final class UpdaterMigrationPortableSqlTest extends TestCase
{
    /**
     * Matches the MariaDB-only `ALTER TABLE ... ADD [COLUMN|KEY|INDEX|...]
     * IF NOT EXISTS` shape. Anchored on `ADD` + up to two keyword tokens +
     * `IF NOT EXISTS`, so `CREATE TABLE IF NOT EXISTS` (valid everywhere)
     * is left alone and punctuation (`;`, backticks) can't be spanned by
     * the `[A-Za-z]+` token class.
     */
    private const ADD_IF_NOT_EXISTS_REGEX = '/\bADD\s+(?:[A-Za-z]+\s+){0,2}IF\s+NOT\s+EXISTS\b/i';

    /**
     * The v2-era floor. Migrations at or above this version are the
     * actively-maintained set every supported upgrade path runs; see the
     * class docblock for why pre-800 migrations are out of scope.
     */
    private const V2_MIGRATION_FLOOR = 800;

    public function testV2MigrationsAvoidMariaDbOnlyAddIfNotExists(): void
    {
        $dataDir = ROOT . 'updater/data';
        $this->assertDirectoryExists($dataDir, 'updater/data/ must live under web/ for the scan to find it');

        $store = $this->loadStore();

        $scanned = [];
        $hits = [];
        foreach ($store as $version => $file) {
            if ((int) $version < self::V2_MIGRATION_FLOOR) {
                continue;
            }

            $file = (string) $file;
            $path = $dataDir . '/' . $file;
            $this->assertFileExists(
                $path,
                "store.json registers migration {$version} => {$file}, but the file is missing.",
            );

            $scanned[] = $file;
            $stripped = php_strip_whitespace($path);
            if (preg_match(self::ADD_IF_NOT_EXISTS_REGEX, $stripped) === 1) {
                $hits[] = "{$file} (version {$version})";
            }
        }

        // Non-vacuous: 801 is the #1498 fix and must be in the scanned set,
        // so a store.json typo / floor change can't make the test pass by
        // scanning nothing.
        $this->assertContains(
            '801.php',
            $scanned,
            'Expected 801.php in the v2 migration scan — store.json or the version floor changed unexpectedly.',
        );

        $this->assertSame(
            0,
            count($hits),
            "v2 migration(s) use MariaDB-only `ALTER TABLE ... ADD ... IF NOT EXISTS`, which MySQL / Percona reject "
                . "with SQLSTATE[42000] 1064 mid-upgrade (issue #1498). Use a portable information_schema existence "
                . "probe + plain `ADD COLUMN` instead (see web/updater/data/801.php for the reference shape).\n\n"
                . "Offending migration(s):\n - "
                . implode("\n - ", $hits),
        );
    }

    /**
     * Decode the migration registry. JSON object keys arrive as PHP array
     * keys (numeric-looking keys become ints); values are filenames.
     *
     * @return array<array-key, mixed>
     */
    private function loadStore(): array
    {
        $storePath = ROOT . 'updater/store.json';
        $this->assertFileExists($storePath);

        $decoded = json_decode((string) file_get_contents($storePath), true);
        $this->assertIsArray($decoded, 'updater/store.json must decode to an array of version => filename.');

        return $decoded;
    }
}
