<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Issue #1578: upgraded installs can lack the `type_authid` / `type_ip`
 * indexes on `:prefix_bans`. `PruneBans()` must not fatal without them, and
 * `web/updater/data/812.php` must (re)create them idempotently.
 */
final class Updater812BansIndexesTest extends ApiTestCase
{
    private const INDEXES = ['type_authid', 'type_ip'];

    protected function tearDown(): void
    {
        $table = DB_PREFIX . '_bans';
        $pdo = Fixture::rawPdo();
        if (!$this->indexExists('type_authid')) {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `type_authid` (`type`, `authid`)");
        }
        if (!$this->indexExists('type_ip')) {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `type_ip` (`type`, `ip`)");
        }

        parent::tearDown();
    }

    private function indexExists(string $index): bool
    {
        $stmt = Fixture::rawPdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([DB_NAME, DB_PREFIX . '_bans', $index]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function dropIndexes(): void
    {
        $table = DB_PREFIX . '_bans';
        foreach (self::INDEXES as $index) {
            Fixture::rawPdo()->exec("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
            $this->assertFalse($this->indexExists($index), "Pre-condition: {$index} was dropped.");
        }
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

        return (bool) $ctx->run(ROOT . 'updater/data/812.php');
    }

    public function testPruneBansRunsWithoutCompositeIndexes(): void
    {
        $this->dropIndexes();

        \PruneBans();

        $this->addToAssertionCount(1);
    }

    public function testMigrationRestoresMissingIndexesAndIsIdempotent(): void
    {
        $this->dropIndexes();

        $this->assertTrue($this->runMigration(), 'First run should succeed.');
        foreach (self::INDEXES as $index) {
            $this->assertTrue($this->indexExists($index), "Migration must add {$index}.");
        }

        $this->assertTrue($this->runMigration(), 'Second run must not fatal on duplicate index.');
    }
}
