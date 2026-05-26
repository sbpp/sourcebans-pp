<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Issue #1472: `web/updater/data/801.php` adds the admin lockout columns.
 *
 * The migration runs inside the Updater instance scope (`$this->dbs` in scope);
 * tests reproduce that shape with an anonymous wrapper. `require` (not
 * require_once) so a test can run the migration twice in one case.
 */
final class Updater801LockoutColumnsTest extends ApiTestCase
{
    private function columnExists(string $column): bool
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([DB_NAME, DB_PREFIX . '_admins', $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function runMigration(): bool
    {
        $ctx = new class($GLOBALS['PDO']) {
            public function __construct(public \Database $dbs) {}

            public function run(string $path): mixed
            {
                return require $path;
            }
        };

        return (bool) $ctx->run(ROOT . 'updater/data/801.php');
    }

    public function testMigrationIsIdempotentWhenLockoutColumnsAlreadyExist(): void
    {
        $this->assertTrue(
            $this->columnExists('attempts'),
            'Pre-condition: test schema should already carry attempts.'
        );
        $this->assertTrue(
            $this->columnExists('lockout_until'),
            'Pre-condition: test schema should already carry lockout_until.'
        );

        $this->assertTrue($this->runMigration(), 'First run should succeed.');
        $this->assertTrue($this->runMigration(), 'Second run must not fatal on duplicate column.');
    }

    public function testMigrationAddsMissingLockoutUntilAfterPartialFailure(): void
    {
        $pdo = Fixture::rawPdo();
        $table = DB_PREFIX . '_admins';
        $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `lockout_until`");

        $this->assertTrue($this->columnExists('attempts'), 'attempts should survive the partial schema.');
        $this->assertFalse($this->columnExists('lockout_until'), 'Pre-condition: lockout_until was dropped.');

        $this->assertTrue($this->runMigration(), 'Migration should complete the partial upgrade.');

        $this->assertTrue(
            $this->columnExists('lockout_until'),
            'Migration must add lockout_until when only that column is missing.'
        );
    }
}
