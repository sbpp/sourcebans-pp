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

        // data.sql already seeds the mods table with mid=1 (Half-Life 2 DM)
        // so we don't need to insert one — just reference an existing row.
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
        $this->assertSame(27015,      (int)$row['port']);

        $del = $this->api('servers.remove', ['sid' => $sid]);
        $this->assertTrue($del['ok']);
        $this->assertNull($this->row('servers', ['sid' => $sid]));
    }
}
