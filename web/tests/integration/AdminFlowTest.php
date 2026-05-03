<?php

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;

/**
 * Tier 1 smoke flow #2 from #1095: admin create + remove via admins.add /
 * admins.remove. Asserts the row makes it into :prefix_admins and is then
 * gone after delete.
 */
final class AdminFlowTest extends ApiTestCase
{
    public function testCreateAndRemoveAdmin(): void
    {
        $this->loginAsAdmin();

        $env = $this->api('admins.add', [
            'mask'             => 0,
            'srv_mask'         => '',
            'name'             => 'Sidekick',
            'steam'            => 'STEAM_0:0:7777',
            'email'            => 'side@kick.test',
            'password'         => 'longpassword',
            'password2'        => 'longpassword',
            'server_group'     => 'c',
            'web_group'        => 'c',
            'server_password'  => '-1',
            'web_name'         => '',
            'server_name'      => '0',
            'servers'          => '',
            'single_servers'   => '',
        ]);

        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertNotEmpty($env['data']['aid']);
        $aid = (int)$env['data']['aid'];

        $row = $this->row('admins', ['aid' => $aid]);
        $this->assertSame('Sidekick',           $row['user']);
        $this->assertSame('side@kick.test',     $row['email']);
        $this->assertSame('STEAM_0:0:7777',     $row['authid']);

        $del = $this->api('admins.remove', ['aid' => $aid]);
        $this->assertTrue($del['ok']);
        $this->assertNull($this->row('admins', ['aid' => $aid]));
    }
}
