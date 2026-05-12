<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

final class CommsTest extends ApiTestCase
{
    public function testAddRejectsAnonymous(): void
    {
        $env = $this->api('comms.add', [
            'nickname' => 'Mouthy',
            'type'     => 1,
            'steam'    => 'STEAM_0:0:1',
            'length'   => 60,
            'reason'   => 'spamming',
        ]);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('comms/add_forbidden', $env);
    }

    public function testAddCreatesGagRow(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('comms.add', [
            'nickname' => 'Mouthy',
            'type'     => 1,
            'steam'    => 'STEAM_0:0:42',
            'length'   => 30,
            'reason'   => 'verbal abuse',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        $rows = $this->rows('comms', ['authid' => 'STEAM_0:0:42']);
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int)$rows[0]['type'], 'gag is type 1');
        $this->assertSame(30 * 60, (int)$rows[0]['length']);
        $this->assertSame(Fixture::adminAid(), (int)$rows[0]['aid']);
        $this->assertSnapshot('comms/add_gag_success', $env);
    }

    public function testAddBothBlockTypeCreatesTwoRows(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('comms.add', [
            'nickname' => 'BothMouthy',
            'type'     => 3,
            'steam'    => 'STEAM_0:0:43',
            'length'   => 0, // permanent
            'reason'   => 'rage typing',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        $rows = $this->rows('comms', ['authid' => 'STEAM_0:0:43']);
        $this->assertCount(2, $rows);
        $types = array_map(fn($r) => (int)$r['type'], $rows);
        sort($types);
        $this->assertSame([1, 2], $types, 'type=3 must insert both gag (1) and mute (2)');
    }

    public function testAddRefusesDuplicateActiveBlock(): void
    {
        $this->loginAsAdmin();
        $params = [
            'nickname' => 'Mouthy',
            'type'     => 1,
            'steam'    => 'STEAM_0:0:99',
            'length'   => 60,
            'reason'   => 'first',
        ];
        $first = $this->api('comms.add', $params);
        $this->assertTrue($first['ok']);

        $params['reason'] = 'second';
        $env = $this->api('comms.add', $params);
        $this->assertEnvelopeError($env, 'already_blocked');
        $this->assertSnapshot('comms/add_already_blocked', $env);
    }

    public function testAddDuplicateBlockErrorIncludesConflictingBid(): void
    {
        // Mirror of BansTest::testAddDuplicateErrorIncludesConflictingBid:
        // pre-seed two unrelated blocks so the conflicting bid isn't `#1`,
        // then assert the message substitutes the captured bid. Pins the
        // contract that the value is actually substituted (not that it
        // happens to render `#1` because of test ordering).
        $this->loginAsAdmin();
        $this->api('comms.add', [
            'nickname' => 'Noise1', 'type' => 1, 'steam' => 'STEAM_0:0:101',
            'length'   => 60, 'reason' => 'noise',
        ]);
        $this->api('comms.add', [
            'nickname' => 'Noise2', 'type' => 1, 'steam' => 'STEAM_0:0:102',
            'length'   => 60, 'reason' => 'noise',
        ]);
        $first = $this->api('comms.add', [
            'nickname' => 'Mouthy', 'type' => 1, 'steam' => 'STEAM_0:0:9999',
            'length'   => 60, 'reason' => 'active-original',
        ]);
        $this->assertTrue($first['ok']);

        $pdo = Fixture::rawPdo();
        $stmt = $pdo->query(sprintf(
            'SELECT bid FROM `%s_comms` WHERE authid = "STEAM_0:0:9999" AND type = 1',
            DB_PREFIX
        ));
        $this->assertNotFalse($stmt);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $conflictBid = (int) $row['bid'];
        $this->assertGreaterThan(1, $conflictBid, 'pre-seeded blocks should have pushed bid past #1');

        $env = $this->api('comms.add', [
            'nickname' => 'Mouthy', 'type' => 1, 'steam' => 'STEAM_0:0:9999',
            'length'   => 60, 'reason' => 'reblock-attempt',
        ]);
        $this->assertEnvelopeError($env, 'already_blocked');
        $this->assertSame(
            'SteamID: STEAM_0:0:9999 is already blocked by block #' . $conflictBid . '.',
            $env['error']['message']
        );
    }

    public function testAddValidatesSteamIdRequired(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('comms.add', ['steam' => '', 'type' => 1]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('steam', $env['error']['field']);
        $this->assertSnapshot('comms/add_validation_steam', $env);
    }

    public function testAddValidatesType(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('comms.add', [
            'steam'  => 'STEAM_0:0:1',
            'type'   => 9, // not 1, 2, or 3
            'length' => 0,
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('type', $env['error']['field']);
    }

    public function testAddRejectsUnrealisticallyLongLength(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('comms.add', [
            'steam'  => 'STEAM_0:0:1',
            'type'   => 1,
            'length' => 60 * 24 * 365 * 200, // 200 years -> exceeds INT range
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('length', $env['error']['field']);
    }

    public function testPrepareReblockReturnsRowOrEmpty(): void
    {
        $this->loginAsAdmin();
        // Seed a block, then prepare-reblock to read it back. The handler
        // is a read-only helper used by the "Re-block" UI button — it
        // returns shape suitable for repopulating the block form.
        $this->api('comms.add', [
            'nickname' => 'Loud',
            'type'     => 2, // mute
            'steam'    => 'STEAM_0:0:55',
            'length'   => 120,
            'reason'   => 'loud noises',
        ]);
        $row = $this->row('comms', ['authid' => 'STEAM_0:0:55']);
        $env = $this->api('comms.prepare_reblock', ['bid' => $row['bid']]);
        $this->assertTrue($env['ok']);
        $this->assertSame((int)$row['bid'], (int)$env['data']['bid']);
        $this->assertSame('STEAM_0:0:55', $env['data']['steam']);
        // type field is row.type - 1 to translate db enum back to UI option.
        $this->assertSame(1, (int)$env['data']['type']);
        $this->assertSnapshot('comms/prepare_reblock_success', $env, ['data.bid']);
    }

    public function testPrepareBlockFromBanReturnsBanRow(): void
    {
        $this->loginAsAdmin();
        // Seed a ban directly (via PDO so we don't depend on bans.add here).
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp)
             VALUES (UNIX_TIMESTAMP(), 0, "", ?, ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1")',
            DB_PREFIX
        ))->execute(['STEAM_0:1:777', 'Cheater', 'aimbot', Fixture::adminAid()]);
        $bid = (int)$pdo->lastInsertId();

        $env = $this->api('comms.prepare_block_from_ban', ['bid' => $bid]);
        $this->assertTrue($env['ok']);
        $this->assertSame($bid, (int)$env['data']['bid']);
        $this->assertSame('STEAM_0:1:777', $env['data']['steam']);
        $this->assertSame('Cheater',       $env['data']['nickname']);
        $this->assertSnapshot('comms/prepare_block_from_ban_success', $env, ['data.bid']);
    }

    public function testPasteRequiresKnownServer(): void
    {
        $this->loginAsAdmin();
        // No servers seeded → rcon('status', 0) returns false → rcon_failed.
        $env = $this->api('comms.paste', ['sid' => 0, 'name' => 'someone']);
        $this->assertEnvelopeError($env, 'rcon_failed');
        $this->assertSnapshot('comms/paste_rcon_failed', $env);
    }

    // -- comms.unblock (#1207 ADM-5/ADM-6) ---------------------------------

    public function testUnblockRejectsAnonymous(): void
    {
        $env = $this->api('comms.unblock', ['bid' => 1]);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('comms/unblock_forbidden', $env);
    }

    public function testUnblockBadRequestOnMissingBid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('comms.unblock', []);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSame('bid', $env['error']['field']);
    }

    public function testUnblockNotFoundForUnknownBid(): void
    {
        $this->loginAsAdmin();
        // #1301 — ureason is now required; supply a non-empty value so
        // the validation gate doesn't short-circuit the row lookup.
        $env = $this->api('comms.unblock', ['bid' => 99999, 'ureason' => 'test']);
        $this->assertEnvelopeError($env, 'not_found');
    }

    /**
     * #1301: a non-empty `ureason` is mandatory. v1.x prompted via
     * sourcebans.js's UnMute/UnGag helpers and required a reason; v2.0
     * silently accepted '', so the audit log lost the *why*.
     */
    public function testUnblockRejectsEmptyUreason(): void
    {
        $this->loginAsAdmin();
        $this->api('comms.add', [
            'nickname' => 'NoReason',
            'type'     => 1,
            'steam'    => 'STEAM_0:0:1301',
            'length'   => 60,
            'reason'   => 'spam',
        ]);
        $row = $this->row('comms', ['authid' => 'STEAM_0:0:1301']);
        $bid = (int)$row['bid'];

        // Missing entirely.
        $env = $this->api('comms.unblock', ['bid' => $bid]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('ureason', $env['error']['field']);

        // Whitespace-only counts as empty after trim().
        $env = $this->api('comms.unblock', ['bid' => $bid, 'ureason' => "   \n\t"]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('ureason', $env['error']['field']);

        // Confirms the row was NOT touched on rejection.
        $after = $this->row('comms', ['bid' => $bid]);
        $this->assertNull($after['RemoveType']);
        $this->assertNull($after['RemovedBy']);
    }

    /**
     * #1301: the audit log carries the unblock reason verbatim.
     */
    public function testUnblockRecordsReasonInAuditLog(): void
    {
        $this->loginAsAdmin();
        $this->api('comms.add', [
            'nickname' => 'Audited',
            'type'     => 2, // gag — drives the "UnGagged" verb in the log
            'steam'    => 'STEAM_0:0:1302',
            'length'   => 60,
            'reason'   => 'spam',
        ]);
        $row = $this->row('comms', ['authid' => 'STEAM_0:0:1302']);
        $bid = (int)$row['bid'];

        $env = $this->api('comms.unblock', [
            'bid'     => $bid,
            'ureason' => 'appeal accepted',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        $logs = $this->rows('log', ['title' => 'Player UnGagged']);
        $this->assertNotEmpty($logs, 'audit log row was created');
        $latest = end($logs);
        $this->assertStringContainsString('appeal accepted', (string) $latest['message']);
        $this->assertStringContainsString('STEAM_0:0:1302', (string) $latest['message']);
    }

    public function testUnblockLiftsActiveGagAndPersistsState(): void
    {
        $this->loginAsAdmin();
        // Seed via the regular comms.add path so we exercise the same
        // insert-side defaults (length seconds, aid linkage, ...) the
        // panel writes in production.
        $this->api('comms.add', [
            'nickname' => 'Mouthy',
            'type'     => 1, // gag
            'steam'    => 'STEAM_0:0:444',
            'length'   => 60,
            'reason'   => 'spam',
        ]);
        $row = $this->row('comms', ['authid' => 'STEAM_0:0:444']);
        $bid = (int)$row['bid'];

        $env = $this->api('comms.unblock', ['bid' => $bid, 'ureason' => 'lifted by e2e']);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame('unmuted',     $env['data']['state']);
        $this->assertSame($bid,          (int)$env['data']['bid']);

        // Persisted: RemoveType='U', RemovedBy=admin aid, ureason stored.
        $after = $this->row('comms', ['bid' => $bid]);
        $this->assertSame('U',                       $after['RemoveType']);
        $this->assertSame(Fixture::adminAid(),       (int)$after['RemovedBy']);
        $this->assertSame('lifted by e2e',           $after['ureason']);
        $this->assertSnapshot('comms/unblock_success', $env, ['data.bid']);
    }

    public function testUnblockRejectsAlreadyLiftedRow(): void
    {
        $this->loginAsAdmin();
        $this->api('comms.add', [
            'nickname' => 'Loud',
            'type'     => 1,
            'steam'    => 'STEAM_0:0:445',
            'length'   => 60,
            'reason'   => 'shouting',
        ]);
        $row = $this->row('comms', ['authid' => 'STEAM_0:0:445']);
        $bid = (int)$row['bid'];

        // #1301: ureason is now required on every unblock attempt.
        $first = $this->api('comms.unblock', ['bid' => $bid, 'ureason' => 'lift it']);
        $this->assertTrue($first['ok']);

        // Second call against the same already-lifted row should refuse.
        $env = $this->api('comms.unblock', ['bid' => $bid, 'ureason' => 'try again']);
        $this->assertEnvelopeError($env, 'not_active');
    }

    // -- comms.delete -------------------------------------------------------

    public function testDeleteRejectsAnonymous(): void
    {
        $env = $this->api('comms.delete', ['bid' => 1]);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('comms/delete_forbidden', $env);
    }

    public function testDeleteBadRequestOnMissingBid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('comms.delete', []);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSame('bid', $env['error']['field']);
    }

    public function testDeleteRemovesActiveRow(): void
    {
        $this->loginAsAdmin();
        $this->api('comms.add', [
            'nickname' => 'Dropme',
            'type'     => 2, // mute
            'steam'    => 'STEAM_0:0:446',
            'length'   => 60,
            'reason'   => 'queue test',
        ]);
        $row = $this->row('comms', ['authid' => 'STEAM_0:0:446']);
        $bid = (int)$row['bid'];

        $env = $this->api('comms.delete', ['bid' => $bid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame($bid, (int)$env['data']['bid']);
        $this->assertTrue($env['data']['deleted']);

        $this->assertNull($this->row('comms', ['bid' => $bid]));
        $this->assertSnapshot('comms/delete_success', $env, ['data.bid']);
    }
}
