<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PDO;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * #1578 — upgraded installs can lack `:prefix_bans`'s `type_authid` /
 * `type_ip` composite indexes. 2.2.1's `PruneBans()` named them in
 * `FORCE INDEX` hints, which MariaDB rejects with error 1176 when the
 * index is missing, fataling the banlist / servers / dashboard pages.
 *
 * Surfaces exercised:
 *   - `PruneBans()` runs and archives matching submissions with both
 *     indexes dropped.
 *   - `web/updater/data/811.php` recreates missing indexes with the
 *     `struc.sql` column order, including the one-of-two partial case.
 *   - Re-running the migration is a no-op (no duplicate indexes).
 *
 * DDL survives `Fixture::truncateAndReseed`, so `tearDown` restores
 * both indexes to keep sibling tests on the fresh-install schema.
 */
final class BansCompositeIndexesTest extends ApiTestCase
{
    private const INDEXES = [
        'type_authid' => ['type', 'authid'],
        'type_ip'     => ['type', 'ip'],
    ];

    protected function tearDown(): void
    {
        $pdo = Fixture::rawPdo();
        foreach (self::INDEXES as $name => $columns) {
            if ($this->indexColumns($name) === []) {
                $pdo->exec(sprintf(
                    'ALTER TABLE `%s_bans` ADD INDEX `%s` (`%s`)',
                    DB_PREFIX,
                    $name,
                    implode('`, `', $columns),
                ));
            }
        }
        parent::tearDown();
    }

    private function runMigration(): bool
    {
        $ctx = new class($GLOBALS['PDO']) {
            public function __construct(public \Database $dbs) {}
            public function run(string $path): mixed { return require $path; }
        };
        return (bool) $ctx->run(ROOT . 'updater/data/811.php');
    }

    private function dropIndex(string $name): void
    {
        Fixture::rawPdo()->exec(sprintf('ALTER TABLE `%s_bans` DROP INDEX `%s`', DB_PREFIX, $name));
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $name): array
    {
        $stmt = Fixture::rawPdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
              ORDER BY SEQ_IN_INDEX'
        );
        $stmt->execute([DB_PREFIX . '_bans', $name]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function bansIndexCount(): int
    {
        $stmt = Fixture::rawPdo()->prepare(
            'SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([DB_PREFIX . '_bans']);
        return (int) $stmt->fetchColumn();
    }

    public function testPruneBansRunsWithoutCompositeIndexes(): void
    {
        $this->dropIndex('type_authid');
        $this->dropIndex('type_ip');

        $pdo = Fixture::rawPdo();
        $now = time();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans`
                (type, ip, authid, name, created, ends, length, reason, aid, adminIp, sid)
             VALUES (?, ?, ?, ?, ?, 0, 0, "test", ?, "127.0.0.1", 0)',
            DB_PREFIX,
        ))->execute([0, null, 'STEAM_0:1:157801', 'active-steam', $now, Fixture::adminAid()]);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans`
                (type, ip, authid, name, created, ends, length, reason, aid, adminIp, sid)
             VALUES (?, ?, ?, ?, ?, 0, 0, "test", ?, "127.0.0.1", 0)',
            DB_PREFIX,
        ))->execute([1, '203.0.113.78', '', 'active-ip', $now, Fixture::adminAid()]);

        $insertSubmission = $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions`
                (submitted, ModID, SteamId, name, email, reason, ip, sip, archiv)
             VALUES (?, 0, ?, ?, "player@example.com", "test", "127.0.0.1", ?, 0)',
            DB_PREFIX,
        ));
        $insertSubmission->execute([$now, 'STEAM_0:1:157801', 'steam-match', null]);
        $insertSubmission->execute([$now, '', 'ip-match', '203.0.113.78']);
        $insertSubmission->execute([$now, 'STEAM_0:1:157899', 'unmatched', null]);

        \PruneBans();

        $rows = $pdo->query(sprintf('SELECT name, archiv FROM `%s_submissions`', DB_PREFIX))
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(3, (int) $rows['steam-match']);
        $this->assertSame(3, (int) $rows['ip-match']);
        $this->assertSame(0, (int) $rows['unmatched']);
    }

    public function testMigrationRecreatesMissingIndexes(): void
    {
        $this->dropIndex('type_authid');
        $this->dropIndex('type_ip');

        $this->assertTrue($this->runMigration());

        foreach (self::INDEXES as $name => $columns) {
            $this->assertSame($columns, $this->indexColumns($name), "$name must match struc.sql");
        }
    }

    public function testMigrationAddsOnlyTheMissingIndex(): void
    {
        $this->dropIndex('type_ip');
        $before = $this->bansIndexCount();

        $this->assertTrue($this->runMigration());

        $this->assertSame(['type', 'authid'], $this->indexColumns('type_authid'));
        $this->assertSame(['type', 'ip'], $this->indexColumns('type_ip'));
        $this->assertSame($before + 1, $this->bansIndexCount());
    }

    public function testMigrationIsNoOpWhenIndexesExist(): void
    {
        $before = $this->bansIndexCount();

        $this->assertTrue($this->runMigration());
        $this->assertTrue($this->runMigration());

        $this->assertSame($before, $this->bansIndexCount());
    }
}
