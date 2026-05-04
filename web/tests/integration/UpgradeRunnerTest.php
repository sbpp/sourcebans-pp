<?php

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sbpp\Migrator\MigrationApplier;
use Sbpp\Migrator\Migrator;
use Sbpp\Tests\Fixture;

/**
 * Issue #1111: integration coverage for the schema migrator.
 *
 * The fixture installs `sourcebans_test` from `web/install/includes/sql/struc.sql`
 * + `data.sql` before each test. We then knock out three different kinds of
 * additions a 1.x → 2.0 upgrade is expected to introduce — a missing
 * column, a missing table, and a missing settings row — and assert that
 * the migrator:
 *
 *   1. Reports them on the dry run (so the `--apply`-less CLI / panel
 *      preview shows operators *exactly* what's coming).
 *   2. Applies them cleanly.
 *   3. Converges on a re-run — the second `plan()` is empty, the second
 *      `apply()` is a no-op. This is the "Nothing to do." guarantee in
 *      the issue's acceptance criteria.
 *
 * The deltas below were chosen to exercise each pathway in the differ
 * (`SchemaDiffer::listExistingTables`, `listExistingColumns`,
 * `diffSettings`) so a regression in any one would break exactly one
 * sub-assertion rather than the whole test.
 */
final class UpgradeRunnerTest extends TestCase
{
    /** @var \PDO Fresh raw PDO held for the lifetime of one test method. */
    private \PDO $pdo;

    private Migrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        // We mutate the schema below (DROP TABLE / DROP COLUMN), so the
        // once-per-process optimisation in `Fixture::install()` would
        // leave the DB inconsistent for subsequent tests. Force a fresh
        // re-install so every test method starts from a clean copy of
        // struc.sql + data.sql.
        Fixture::forgetInstallFlag();
        Fixture::reset();

        $this->pdo = Fixture::rawPdo();
        $this->migrator = Migrator::fromInstallSql(ROOT, DB_PREFIX, DB_CHARSET);
    }

    public function testFreshSchemaIsAlreadyUpToDate(): void
    {
        $plan = $this->migrator->plan($this->pdo);
        $this->assertTrue(
            $plan->isEmpty(),
            'A freshly-installed `sourcebans_test` should match struc.sql: '
            . json_encode($plan->toArray(), JSON_PRETTY_PRINT),
        );
    }

    public function testDropAndConvergeForColumnTableAndSetting(): void
    {
        // --- 1. Knock out one of each kind of 2.0 addition. ----------
        // Missing column: `lockout_until` — added by the 1.6 → 1.8
        //   lockout work and shipped in struc.sql.
        // Missing table:  `:prefix_login_tokens` — JWT GC table added
        //   in the 1.6 → 1.7 jump.
        // Missing setting: `config.enablesteamlogin` — issue #1102 work.
        $this->dropColumn(DB_PREFIX . '_admins', 'lockout_until');
        $this->dropTable(DB_PREFIX . '_login_tokens');
        $this->deleteSetting('config.enablesteamlogin');

        // --- 2. Dry run reports all three. ----------------------------
        $plan = $this->migrator->plan($this->pdo);

        $this->assertSame(3, $plan->totalChanges(),
            'Expected exactly 3 deltas (1 col + 1 table + 1 setting), got: '
            . json_encode($plan->toArray(), JSON_PRETTY_PRINT));

        $tableNames = array_map(fn($t) => $t->table, $plan->missingTables);
        $this->assertSame(['login_tokens'], $tableNames,
            'Missing-table set should contain `login_tokens`.');

        $columnDescriptors = array_map(
            fn($c) => $c->table . '.' . $c->column,
            $plan->missingColumns,
        );
        $this->assertSame(['admins.lockout_until'], $columnDescriptors,
            'Missing-column set should contain `admins.lockout_until`.');

        $settingKeys = array_map(fn($s) => $s->key, $plan->missingSettings);
        $this->assertSame(['config.enablesteamlogin'], $settingKeys,
            'Missing-setting set should contain `config.enablesteamlogin`.');

        // The DDL placeholders must already be substituted; the applier
        // hands these straight to PDO.
        $tableSql = $plan->missingTables[0]->sql;
        $this->assertStringNotContainsString('{prefix}', $tableSql,
            'Plan SQL must have {prefix} substituted before reaching apply.');
        $this->assertStringNotContainsString('{charset}', $tableSql,
            'Plan SQL must have {charset} substituted before reaching apply.');
        $this->assertStringContainsString('`' . DB_PREFIX . '_login_tokens`', $tableSql);

        // --- 3. Apply, every step succeeds. --------------------------
        $applier = new MigrationApplier(DB_PREFIX);
        $result  = $applier->apply($this->pdo, $plan);

        $this->assertTrue($result->success,
            'Apply should succeed; error was: ' . ($result->error ?? '<null>'));
        $this->assertSame(3, count($result->steps),
            'Expected one step per delta. Got: '
            . json_encode($result->toArray(), JSON_PRETTY_PRINT));
        foreach ($result->steps as $step) {
            $this->assertTrue($step->success, "Step '{$step->summary}' should have succeeded.");
        }

        // The deltas physically landed in the live DB.
        $this->assertTrue($this->columnExists(DB_PREFIX . '_admins', 'lockout_until'));
        $this->assertTrue($this->tableExists(DB_PREFIX . '_login_tokens'));
        $this->assertSame('1', $this->readSetting('config.enablesteamlogin'),
            "Backfilled setting should match data.sql's seed value.");

        // --- 4. Convergence: re-running is a clean no-op. ------------
        $rePlan = $this->migrator->plan($this->pdo);
        $this->assertTrue($rePlan->isEmpty(),
            'Re-planning after apply should yield an empty plan; got: '
            . json_encode($rePlan->toArray(), JSON_PRETTY_PRINT));

        $reApply = $applier->apply($this->pdo, $rePlan);
        $this->assertTrue($reApply->success);
        $this->assertSame([], $reApply->steps,
            'Empty plan should produce zero apply steps.');
    }

    public function testApplyHonoursExistingSettingValues(): void
    {
        // Operators routinely tweak settings (e.g. flipping
        // `config.enablesteamlogin` off). The migrator must never clobber
        // an admin-edited value when re-running. We INSERT IGNORE on the
        // `setting` UNIQUE key, so an existing row stays intact even if
        // the seed shipped a different value.
        $this->setSetting('config.enablesteamlogin', '0');

        // Now drop *another* setting so the planner still has work to
        // do; otherwise the apply path is trivially a no-op.
        $this->deleteSetting('config.enablenormallogin');

        $plan = $this->migrator->plan($this->pdo);
        $applier = new MigrationApplier(DB_PREFIX);
        $applier->apply($this->pdo, $plan);

        $this->assertSame('0', $this->readSetting('config.enablesteamlogin'),
            'Apply must not overwrite an admin-customised setting.');
        $this->assertSame('1', $this->readSetting('config.enablenormallogin'),
            'Apply must backfill the missing setting from data.sql.');
    }

    public function testTableDropImpliesAllSettingsRebuild(): void
    {
        // Edge case: if the entire `:prefix_settings` table is missing
        // (which would only happen on an extraordinarily corrupt panel,
        // but the migrator should still cope), the planner should queue
        // the CREATE TABLE *and* every seed row, and the applier should
        // run them in the right order so the inserts find a target.
        $this->dropTable(DB_PREFIX . '_settings');

        $plan = $this->migrator->plan($this->pdo);

        $tableNames = array_map(fn($t) => $t->table, $plan->missingTables);
        $this->assertContains('settings', $tableNames,
            'Missing the settings table should be reported.');

        $this->assertNotEmpty($plan->missingSettings,
            'When the settings table is gone, every seed row should be queued.');

        $applier = new MigrationApplier(DB_PREFIX);
        $result  = $applier->apply($this->pdo, $plan);

        $this->assertTrue($result->success,
            'Settings-table rebuild should succeed; error was: ' . ($result->error ?? '<null>'));

        // And convergence still holds.
        $this->assertTrue($this->migrator->plan($this->pdo)->isEmpty());
    }

    private function dropColumn(string $table, string $column): void
    {
        $this->pdo->exec(sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $table, $column));
    }

    private function dropTable(string $table): void
    {
        $this->pdo->exec(sprintf('DROP TABLE `%s`', $table));
    }

    private function deleteSetting(string $key): void
    {
        $stmt = $this->pdo->prepare(sprintf(
            'DELETE FROM `%s_settings` WHERE `setting` = ?',
            DB_PREFIX,
        ));
        $stmt->execute([$key]);
    }

    private function setSetting(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`setting`, `value`) VALUES (?, ?)',
            DB_PREFIX,
        ));
        $stmt->execute([$key, $value]);
    }

    private function readSetting(string $key): ?string
    {
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT `value` FROM `%s_settings` WHERE `setting` = ?',
            DB_PREFIX,
        ));
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : (string) $row['value'];
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        );
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
