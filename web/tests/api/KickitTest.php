<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Smoke coverage for the read-mostly kickit handlers (admin.kickit.php
 * iframe). Mirrors BlockitTest — same wire format ("status", "sid",
 * "num", optional "ip"/"port"/"hostname") so the iframe can reuse one
 * renderer for both flows.
 */
final class KickitTest extends ApiTestCase
{
    public function testLoadServersRejectsAnonymous(): void
    {
        $env = $this->api('kickit.load_servers', []);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('kickit/load_servers_forbidden', $env);
    }

    public function testLoadServersReturnsEmptyListForNoServers(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('kickit.load_servers', []);
        $this->assertTrue($env['ok']);
        $this->assertSame([], $env['data']['servers']);
        $this->assertSnapshot('kickit/load_servers_empty', $env);
    }

    public function testLoadServersListsEnabledServers(): void
    {
        $this->loginAsAdmin();
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (sid, ip, port, rcon, modid, enabled) VALUES (?, ?, ?, ?, 1, 1)',
            DB_PREFIX
        ))->execute([5, '10.10.0.1', 27015, 'rcon5']);

        $env = $this->api('kickit.load_servers', []);
        $this->assertTrue($env['ok']);
        $this->assertCount(1, $env['data']['servers']);
        $this->assertSame(5, (int)$env['data']['servers'][0]['sid']);
        $this->assertTrue($env['data']['servers'][0]['has_rcon']);
        $this->assertSnapshot('kickit/load_servers_one_enabled', $env);
    }

    public function testKickPlayerNoConnectForUnknownServer(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('kickit.kick_player', [
            'check' => 'STEAM_0:0:1',
            'sid'   => 0,
            'num'   => 0,
            'type'  => 0,
        ]);
        $this->assertTrue($env['ok']);
        $this->assertSame('no_connect', $env['data']['status']);
        $this->assertSnapshot('kickit/kick_player_no_connect', $env);
    }

    public function testKickPlayerRejectsAnonymous(): void
    {
        $env = $this->api('kickit.kick_player', ['check' => 'STEAM_0:0:1', 'sid' => 0]);
        $this->assertEnvelopeError($env, 'forbidden');
    }
}
