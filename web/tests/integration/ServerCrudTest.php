<?php

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Tier 1 smoke flow #4 from #1095: a single server CRUD round-trip:
 * add -> verify row -> remove -> verify gone.
 */
final class ServerCrudTest extends ApiTestCase
{
    public function testAddAndRemoveServer(): void
    {
        $this->loginAsAdmin();

        // Need at least one mod row so the FK on modid validates downstream usage.
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%smods` (mid, name, icon, modfolder, steam_universe, enabled) VALUES (1, ?, ?, ?, 0, 1)',
            DB_PREFIX
        ))->execute(['CSGO', 'csgo.png', 'csgo']);

        $add = $this->api('servers.add', [
            'ip'      => '10.0.0.1',
            'port'    => '27015',
            'rcon'    => 'secret',
            'rcon2'   => 'secret',
            'mod'     => 1,
            'enabled' => true,
            'group'   => '0',
        ]);
        $this->assertTrue($add['ok'], json_encode($add));
        $sid = (int)$add['data']['sid'];

        $row = $this->row('servers', ['sid' => $sid]);
        $this->assertSame('10.0.0.1', $row['ip']);
        $this->assertSame('27015',    $row['port']);

        $del = $this->api('servers.remove', ['sid' => $sid]);
        $this->assertTrue($del['ok']);
        $this->assertNull($this->row('servers', ['sid' => $sid]));
    }
}
