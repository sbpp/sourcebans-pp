<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

namespace Sbpp\Tests\Integration;

use PDO;
use Sbpp\Db\Database;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Regression coverage for MariaDB error 1390 ("Prepared statement
 * contains too many placeholders") on large installs.
 */
final class DatabaseInListChunkTest extends ApiTestCase
{
    public function testResultsetInListChunksSeventyFiveThousandValues(): void
    {
        $boundaryAids = $this->insertBoundaryAdmins();
        $values = range(1, 75_000);

        Database::resetQueryCount();
        $aids = $GLOBALS['PDO']->resultsetInList(
            'SELECT aid FROM `:prefix_admins` WHERE aid IN (',
            $values,
            ') ORDER BY aid',
            fetchType: PDO::FETCH_COLUMN,
        );

        $this->assertSame(
            [Fixture::adminAid(), ...$boundaryAids],
            array_map(static fn ($aid): int => (int) $aid, $aids),
        );
        $this->assertSame(8, Database::getQueryCount());
    }

    public function testResultsetInListSkipsEmptyValuesWithoutPreparingSql(): void
    {
        Database::resetQueryCount();

        $rows = $GLOBALS['PDO']->resultsetInList(
            'SELECT aid FROM `:prefix_admins` WHERE aid IN (',
            [],
            ')',
        );

        $this->assertSame([], $rows);
        $this->assertSame(0, Database::getQueryCount());
    }

    public function testResultsetInListRejectsKeyedFetchModes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PDO::FETCH_ASSOC and PDO::FETCH_COLUMN');

        $GLOBALS['PDO']->resultsetInList(
            'SELECT aid, user FROM `:prefix_admins` WHERE aid IN (',
            range(1, 20_001),
            ')',
            fetchType: PDO::FETCH_KEY_PAIR,
        );
    }

    public function testExecuteInListChunksSeventyFiveThousandValuesWithFixedParameter(): void
    {
        $boundaryAids = $this->insertBoundaryAdmins();
        $values = range(1, 75_000);

        Database::resetQueryCount();
        $affected = $GLOBALS['PDO']->executeInList(
            'UPDATE `:prefix_admins` SET lastvisit = ? WHERE aid IN (',
            $values,
            ')',
            [1_750_000_000],
        );

        $this->assertSame(4, $affected);
        $this->assertSame(8, Database::getQueryCount());
        $updatedRows = Fixture::rawPdo()->query(sprintf(
            'SELECT aid, lastvisit FROM `%s_admins` WHERE aid > 0 ORDER BY aid',
            DB_PREFIX,
        ))->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(
            [Fixture::adminAid(), ...$boundaryAids],
            array_map('intval', array_keys($updatedRows)),
        );
        foreach ($updatedRows as $lastVisit) {
            $this->assertSame(1_750_000_000, (int) $lastVisit);
        }
    }

    public function testAtomicExecuteInListRollsBackEarlierChunkWhenLaterChunkFails(): void
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins`
                (aid, user, password, gid, email, extraflags)
             VALUES (10001, "boundary-10001", "", -1, "boundary-10001@example.test", 0)',
            DB_PREFIX,
        ))->execute();

        $failure = null;
        try {
            $GLOBALS['PDO']->executeInList(
                'UPDATE `:prefix_admins` SET user = "chunk-collision" WHERE aid IN (',
                range(1, 10_001),
                ')',
                atomic: true,
            );
        } catch (\PDOException $e) {
            $failure = $e;
        }

        $this->assertInstanceOf(\PDOException::class, $failure);
        $users = $pdo->query(sprintf(
            'SELECT aid, user FROM `%s_admins` WHERE aid IN (1, 10001) ORDER BY aid',
            DB_PREFIX,
        ))->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame([1 => 'admin', 10001 => 'boundary-10001'], $users);
    }

    public function testPruneBansArchivesOnlySubmissionsMatchingActiveBans(): void
    {
        $pdo = Fixture::rawPdo();
        $aid = Fixture::adminAid();
        $now = time();

        $insertBan = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans`
                (type, ip, authid, name, created, ends, length, reason, aid, adminIp, sid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "127.0.0.1", 0)',
            DB_PREFIX,
        ));
        $insertBan->execute([0, null, 'STEAM_0:1:75001', 'active-steam', $now, 0, 0, 'test', $aid]);
        $insertBan->execute([1, '203.0.113.75', '', 'active-ip', $now, 0, 0, 'test', $aid]);
        $insertBan->execute([0, null, 'STEAM_0:1:75002', 'expired-steam', $now - 120, $now - 60, 60, 'test', $aid]);

        $insertSubmission = $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions`
                (submitted, ModID, SteamId, name, email, reason, ip, sip, archiv)
             VALUES (?, 0, ?, ?, "player@example.com", "test", "127.0.0.1", ?, 0)',
            DB_PREFIX,
        ));
        $insertSubmission->execute([$now, 'STEAM_0:1:75001', 'steam-match', null]);
        $insertSubmission->execute([$now, '', 'ip-match', '203.0.113.75']);
        $insertSubmission->execute([$now, 'STEAM_0:1:75002', 'expired-no-match', null]);
        $insertSubmission->execute([$now, 'STEAM_0:1:75999', 'unmatched', null]);

        \PruneBans();

        $submissionRows = $pdo->query(sprintf(
            'SELECT name, archiv, archivedby FROM `%s_submissions` ORDER BY subid',
            DB_PREFIX,
        ))->fetchAll(PDO::FETCH_ASSOC);
        $submissions = array_column($submissionRows, null, 'name');

        $this->assertSame(3, (int) $submissions['steam-match']['archiv']);
        $this->assertSame(3, (int) $submissions['ip-match']['archiv']);
        $this->assertSame(0, (int) $submissions['steam-match']['archivedby']);
        $this->assertSame(0, (int) $submissions['expired-no-match']['archiv']);
        $this->assertSame(0, (int) $submissions['unmatched']['archiv']);

        $expired = $pdo->query(sprintf(
            "SELECT RemoveType FROM `%s_bans` WHERE authid = 'STEAM_0:1:75002'",
            DB_PREFIX,
        ))->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('E', $expired['RemoveType']);
    }

    /**
     * @return list<int>
     */
    private function insertBoundaryAdmins(): array
    {
        $aids = [10_000, 10_001, 75_000];
        $insert = Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins`
                (aid, user, password, gid, email, extraflags)
             VALUES (?, ?, "", -1, ?, 0)',
            DB_PREFIX,
        ));
        foreach ($aids as $aid) {
            $insert->execute([$aid, 'boundary-' . $aid, 'boundary-' . $aid . '@example.test']);
        }

        return $aids;
    }
}
