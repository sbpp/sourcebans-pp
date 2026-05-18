<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * #1352 — `web/updater/data/810.php` backfills `:prefix_bans.RemoveType`
 * for pre-2.0 rows that carry `RemovedOn IS NOT NULL` but
 * `RemoveType IS NULL`. Two flavours land in this bucket,
 * distinguished by `RemovedBy`:
 *
 *   1. Admin-lifted bans (`RemovedBy IS NOT NULL AND RemovedBy > 0`) →
 *      tagged `'U'`.
 *   2. Naturally-expired bans (`RemovedBy IS NULL OR RemovedBy = 0`) →
 *      tagged `'E'`.
 *
 * Surfaces exercised:
 *   - Admin-lifted rows are converted to RemoveType='U'.
 *   - Natural-expiry rows (RemovedBy=0) are converted to RemoveType='E'.
 *   - Already-tagged rows (D/U/E) are untouched.
 *   - Active rows (RemovedOn IS NULL) are untouched.
 *   - Re-running the migration is a no-op (idempotency contract from
 *     AGENTS.md "Updater migrations").
 *   - The two passes don't cross-contaminate (an admin-lifted row
 *     never gets retagged 'E', and vice versa).
 *
 * Mirrors `UpgradeThemeResetTest`'s migration-runner shape.
 */
final class UpdaterBackfillRemoveTypeTest extends ApiTestCase
{
    private function runMigration(): bool
    {
        // The migration is `require_once`'d inside the Updater
        // instance scope so `$this->dbs` is in scope. Reproduce the
        // same shape with an anonymous class. `require` (not
        // require_once) so this test can run after the production
        // updater path has already loaded the file.
        $ctx = new class($GLOBALS['PDO']) {
            public function __construct(public \Database $dbs) {}
            public function run(string $path): mixed { return require $path; }
        };
        return (bool) $ctx->run(ROOT . 'updater/data/810.php');
    }

    /**
     * @param int|null $removedBy
     * @param int|null $removedOn
     * @param string|null $removeType
     */
    private function insertBan(
        ?int $removedBy,
        ?int $removedOn,
        ?string $removeType,
        string $authid = 'STEAM_0:1:99001',
    ): int {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (0, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX,
        ));
        $now = time();
        $stmt->execute([
            $authid,
            'TestBan',
            $now - 7 * 86400,
            $now + 7 * 86400,
            14 * 86400,
            'test',
            'test ureason',
            Fixture::adminAid(),
            $removedBy,
            $removedOn,
            $removeType,
        ]);
        return (int) $pdo->lastInsertId();
    }

    private function readRemoveType(int $bid): ?string
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'SELECT RemoveType FROM `%s_bans` WHERE bid = ?',
            DB_PREFIX,
        ));
        $stmt->execute([$bid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return $row['RemoveType'] === null ? null : (string) $row['RemoveType'];
    }

    public function testAdminLiftRowGetsRemoveTypeU(): void
    {
        // The marquee bug: pre-2.0 v1.x panels left RemoveType
        // NULL even when admin lifted the ban. Migration must
        // tag these as 'U'.
        $aid = Fixture::adminAid();
        $bid = $this->insertBan(
            removedBy: $aid,
            removedOn: time() - 86400,
            removeType: null,
            authid: 'STEAM_0:1:99100',
        );

        $this->assertNull($this->readRemoveType($bid),
            'Pre-condition: RemoveType must be NULL before the migration runs');

        $this->assertTrue($this->runMigration(), 'Migration must report success');

        $this->assertSame('U', $this->readRemoveType($bid),
            'Admin-lifted row (RemovedBy > 0) must be tagged \'U\' post-migration');
    }

    public function testNaturalExpiryRowWithRemovedByZeroGetsRemoveTypeE(): void
    {
        // PruneBans() writes `RemovedBy = 0` for natural expiry; if
        // the column was set but RemoveType wasn't (e.g. the prune
        // ran on an old fork that didn't set it), the migration
        // backfills 'E'.
        $bid = $this->insertBan(
            removedBy: 0,
            removedOn: time() - 86400,
            removeType: null,
            authid: 'STEAM_0:1:99200',
        );

        $this->runMigration();

        $this->assertSame('E', $this->readRemoveType($bid),
            'Natural-expiry row (RemovedBy = 0) must be tagged \'E\' post-migration');
    }

    public function testNaturalExpiryRowWithRemovedByNullGetsRemoveTypeE(): void
    {
        // Pre-475 installs that didn't have the RemoveType column
        // also wouldn't have populated RemovedBy reliably for
        // natural expiry. The OR clause in pass 2 covers this
        // shape (`RemovedBy IS NULL`).
        $bid = $this->insertBan(
            removedBy: null,
            removedOn: time() - 86400,
            removeType: null,
            authid: 'STEAM_0:1:99201',
        );

        $this->runMigration();

        $this->assertSame('E', $this->readRemoveType($bid),
            'Natural-expiry row (RemovedBy IS NULL) must be tagged \'E\' post-migration');
    }

    public function testAlreadyTaggedRowIsUntouched(): void
    {
        // The WHERE pins `RemoveType IS NULL`, so already-tagged
        // rows are excluded from BOTH passes — no-op.
        $bidU = $this->insertBan(
            removedBy: Fixture::adminAid(),
            removedOn: time() - 86400,
            removeType: 'U',
            authid: 'STEAM_0:1:99301',
        );
        $bidD = $this->insertBan(
            removedBy: Fixture::adminAid(),
            removedOn: time() - 86400,
            removeType: 'D',
            authid: 'STEAM_0:1:99302',
        );
        $bidE = $this->insertBan(
            removedBy: 0,
            removedOn: time() - 86400,
            removeType: 'E',
            authid: 'STEAM_0:1:99303',
        );

        $this->runMigration();

        $this->assertSame('U', $this->readRemoveType($bidU),
            'Already-tagged \'U\' must survive the migration unchanged');
        $this->assertSame('D', $this->readRemoveType($bidD),
            'Already-tagged \'D\' must survive the migration unchanged');
        $this->assertSame('E', $this->readRemoveType($bidE),
            'Already-tagged \'E\' must survive the migration unchanged');
    }

    public function testActiveRowIsUntouched(): void
    {
        // RemovedOn IS NULL means the ban is still active — neither
        // pass should touch it.
        $bid = $this->insertBan(
            removedBy: null,
            removedOn: null,
            removeType: null,
            authid: 'STEAM_0:1:99400',
        );

        $this->runMigration();

        $this->assertNull($this->readRemoveType($bid),
            'Active row (RemovedOn IS NULL) must remain RemoveType=NULL');
    }

    public function testRerunIsNoOp(): void
    {
        // After the first pass tags the row, the second run's
        // WHERE excludes it (RemoveType is no longer NULL), so
        // the second run matches zero rows — the idempotency
        // contract every updater migration must satisfy
        // (AGENTS.md "Updater migrations").
        $aid = Fixture::adminAid();
        $bid = $this->insertBan(
            removedBy: $aid,
            removedOn: time() - 86400,
            removeType: null,
            authid: 'STEAM_0:1:99500',
        );

        $this->assertTrue($this->runMigration(), 'First run must succeed');
        $this->assertSame('U', $this->readRemoveType($bid));

        $this->assertTrue($this->runMigration(), 'Re-run must still report success');
        $this->assertSame('U', $this->readRemoveType($bid),
            'Re-running must NOT change the tag (the WHERE excludes already-tagged rows)');
    }

    public function testTwoPassesDoNotCrossContaminate(): void
    {
        // A row that fits the admin-lift shape (RemovedBy > 0)
        // must get 'U', NOT 'E'. A row that fits the natural-
        // expiry shape (RemovedBy = 0) must get 'E', NOT 'U'.
        // The order of the two UPDATE statements matters here:
        // the admin-lift pass runs first so a row with
        // RemovedBy > 0 is already tagged 'U' before pass 2's
        // WHERE checks RemoveType IS NULL.
        $aid = Fixture::adminAid();

        $liftedBid = $this->insertBan(
            removedBy: $aid,
            removedOn: time() - 86400,
            removeType: null,
            authid: 'STEAM_0:1:99601',
        );
        $expiredBid = $this->insertBan(
            removedBy: 0,
            removedOn: time() - 86400,
            removeType: null,
            authid: 'STEAM_0:1:99602',
        );

        $this->runMigration();

        $this->assertSame('U', $this->readRemoveType($liftedBid),
            'Admin-lifted row must end up \'U\', NOT \'E\' (pass 1 wins)');
        $this->assertSame('E', $this->readRemoveType($expiredBid),
            'Natural-expiry row must end up \'E\' (pass 1\'s WHERE excludes it; pass 2 picks it up)');
    }

    public function testMigrationReturnsTrueOnEmptyTable(): void
    {
        // Fresh installs (or installs that already converged on
        // the canonical shape) have nothing for either pass to
        // do. The migration must still return true so the
        // updater runner advances `config.version` past 810.
        $this->assertTrue($this->runMigration(),
            'Migration must report success even when both passes match zero rows');
    }
}
