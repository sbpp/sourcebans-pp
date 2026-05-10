<?php

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Issue #1307: v2.0.0 ships a complete chrome rewrite (#1123 / #1207
 * / #1259 / #1275). Fork themes inherited from v1.x do not contain
 * the v2.0 templates and fatal on the first post-upgrade page load.
 *
 * `web/updater/data/808.php` resets `:prefix_settings.config.theme`
 * to `'default'` so the panel actually loads after the upgrade.
 *
 * Four properties exercised here:
 *   1. A fork theme value is rewritten to 'default' on first run.
 *   2. An install already on 'default' is left untouched (the WHERE
 *      clause matches no rows).
 *   3. Re-running the migration immediately after the first pass is
 *      a no-op (the WHERE clause excludes the now-default row).
 *   4. The default value the migration writes matches what
 *      `data.sql` seeds for fresh installs (so fresh and upgraded
 *      installs converge).
 */
final class UpgradeThemeResetTest extends ApiTestCase
{
    private function setSetting(string $key, string $value): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`setting`, `value`) VALUES (?, ?)',
            DB_PREFIX
        ));
        $stmt->execute([$key, $value]);
        \Config::init($GLOBALS['PDO']);
    }

    private function readSetting(string $key): ?string
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'SELECT value FROM `%s_settings` WHERE `setting` = ?',
            DB_PREFIX
        ));
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : (string) $row['value'];
    }

    private function runMigration(): bool
    {
        // The migration is `require_once`'d inside the Updater instance
        // scope so `$this->dbs` is in scope. Reproduce the same shape with
        // an anonymous class. `require` (not require_once) so this test
        // can run after the production updater path has already loaded
        // the file.
        $ctx = new class($GLOBALS['PDO']) {
            public function __construct(public \Database $dbs) {}
            public function run(string $path): mixed { return require $path; }
        };
        return (bool) $ctx->run(ROOT . 'updater/data/808.php');
    }

    public function testForkThemeIsResetToDefault(): void
    {
        // Mirror a v1.x install that's been pointing at a forked theme
        // directory (e.g. `tf2c`, `darkred`) — the panel will fatal on
        // the first post-upgrade render against missing v2.0 templates
        // unless the migration converges this back to 'default'.
        $this->setSetting('config.theme', 'tf2c');
        $this->assertSame('tf2c', $this->readSetting('config.theme'),
            'Pre-condition: the fork theme should be set before the migration runs.');

        $this->assertTrue($this->runMigration(), 'Migration should report success.');

        $this->assertSame('default', $this->readSetting('config.theme'),
            'Migration must rewrite a fork theme value back to default so v2.0 chrome renders.');
    }

    public function testInstallAlreadyOnDefaultIsUntouched(): void
    {
        // data.sql seeds 'default'; an install that was already on the
        // shipped theme should be a no-op for the migration. The WHERE
        // clause excludes the row, so the underlying UPDATE matches 0
        // rows on this path.
        $this->setSetting('config.theme', 'default');
        $this->assertSame('default', $this->readSetting('config.theme'));

        $this->assertTrue($this->runMigration());

        $this->assertSame('default', $this->readSetting('config.theme'),
            'Migration must not touch an install already on default.');
    }

    public function testRerunImmediatelyAfterFirstPassIsNoOp(): void
    {
        // After the first run leaves the row at 'default', the WHERE
        // clause excludes that row and the second run matches zero rows.
        // This is the idempotency guarantee the migration relies on per
        // AGENTS.md "Updater migrations" — `Updater` has no rollback,
        // partial state must be safe to re-run, and the runner itself
        // skips anything <= config.version on a healthy upgrade.
        $this->setSetting('config.theme', 'tf2c');

        $this->assertTrue($this->runMigration(), 'First run should succeed.');
        $this->assertSame('default', $this->readSetting('config.theme'));

        $this->assertTrue($this->runMigration(), 'Re-run should still report success.');
        $this->assertSame('default', $this->readSetting('config.theme'),
            'Re-running must be a no-op (the WHERE clause excludes the row once it holds default).');
    }

    public function testMigrationDefaultMatchesDataSqlSeed(): void
    {
        // `data.sql` line 48 seeds `('config.theme', 'default')`. The
        // migration writes the same string. Lock that parity in: a
        // future rename of the shipped theme directory would otherwise
        // diverge fresh installs (data.sql) from upgraded installs
        // (this migration).
        $dataSql = (string) file_get_contents(ROOT . 'install/includes/sql/data.sql');
        $this->assertNotSame('', $dataSql, 'Could not read install/includes/sql/data.sql');

        $this->assertMatchesRegularExpression(
            "/'config\\.theme',\\s*'default'/",
            $dataSql,
            'data.sql seed for config.theme drifted away from "default" — update the migration too.'
        );

        $this->setSetting('config.theme', 'tf2c');
        $this->runMigration();

        $this->assertSame('default', $this->readSetting('config.theme'),
            'Migration default value must match the data.sql seed for fresh installs.');
    }
}
