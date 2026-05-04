<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Smoke coverage for the read-mostly blockit handlers (admin.blockit.php
 * iframe). The block_player path goes through rcon() — without a live
 * gameserver the call returns the structured `no_connect` envelope,
 * which is the contract the iframe uses to decide whether to show a
 * "server unreachable" badge per row.
 */
final class BlockitTest extends ApiTestCase
{
    public function testLoadServersRejectsAnonymous(): void
    {
        $env = $this->api('blockit.load_servers', []);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('blockit/load_servers_forbidden', $env);
    }

    public function testLoadServersReturnsEmptyListForNoServers(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('blockit.load_servers', []);
        $this->assertTrue($env['ok']);
        $this->assertSame([], $env['data']['servers']);
        $this->assertSnapshot('blockit/load_servers_empty', $env);
    }

    public function testLoadServersListsEnabledServers(): void
    {
        $this->loginAsAdmin();
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (sid, ip, port, rcon, modid, enabled) VALUES (?, ?, ?, ?, 1, 1)',
            DB_PREFIX
        ))->execute([1, '10.0.0.1', 27015, 'has-rcon']);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (sid, ip, port, rcon, modid, enabled) VALUES (?, ?, ?, ?, 1, 1)',
            DB_PREFIX
        ))->execute([2, '10.0.0.2', 27016, '']);
        // Disabled — must not appear in the response.
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (sid, ip, port, rcon, modid, enabled) VALUES (?, ?, ?, ?, 1, 0)',
            DB_PREFIX
        ))->execute([3, '10.0.0.3', 27017, 'rcon3']);

        $env = $this->api('blockit.load_servers', []);
        $this->assertTrue($env['ok']);
        $this->assertCount(2, $env['data']['servers']);
        $this->assertSame(1, (int)$env['data']['servers'][0]['sid']);
        $this->assertTrue($env['data']['servers'][0]['has_rcon']);
        $this->assertFalse($env['data']['servers'][1]['has_rcon']);
        $this->assertSnapshot('blockit/load_servers_two_enabled', $env);
    }

    public function testBlockPlayerNoConnectForUnknownServer(): void
    {
        $this->loginAsAdmin();
        // No servers seeded → rcon('status', 0) returns false → no_connect
        // wire envelope. The iframe consumes this to render a per-row
        // "server offline" indicator.
        $env = $this->api('blockit.block_player', [
            'check' => 'STEAM_0:0:1',
            'sid'   => 0,
            'num'   => 0,
            'type'  => 1,
            'length' => 60,
        ]);
        $this->assertTrue($env['ok']);
        $this->assertSame('no_connect', $env['data']['status']);
        $this->assertSnapshot('blockit/block_player_no_connect', $env);
    }

    public function testBlockPlayerRejectsAnonymous(): void
    {
        $env = $this->api('blockit.block_player', ['check' => 'STEAM_0:0:1', 'sid' => 0]);
        $this->assertEnvelopeError($env, 'forbidden');
    }
}
