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
}
