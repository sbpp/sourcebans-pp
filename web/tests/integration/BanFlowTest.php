<?php

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Tier 1 smoke flow #1 from #1095: an admin creates a ban via bans.add and
 * the row appears in :prefix_bans with the admin as :aid.
 */
final class BanFlowTest extends ApiTestCase
{
    public function testCreateBanWritesBansRow(): void
    {
        $this->loginAsAdmin();

        $env = $this->api('bans.add', [
            'nickname' => 'Cheater',
            'type'     => 0,
            'steam'    => 'STEAM_0:1:99999',
            'ip'       => '',
            'length'   => 60,           // minutes
            'dfile'    => '',
            'dname'    => '',
            'reason'   => 'aimbot',
            'fromsub'  => 0,
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertNotEmpty($env['data']['bid']);

        $ban = $this->row('bans', ['bid' => $env['data']['bid']]);
        $this->assertSame('STEAM_0:1:99999', $ban['authid']);
        $this->assertSame('aimbot', $ban['reason']);
        $this->assertSame(Fixture::adminAid(), (int)$ban['aid']);
    }

    public function testDuplicateSteamRejected(): void
    {
        $this->loginAsAdmin();
        $first = $this->api('bans.add', [
            'nickname' => 'Cheater',
            'type'     => 0,
            'steam'    => 'STEAM_0:1:88888',
            'ip'       => '',
            'length'   => 0,
            'dfile'    => '',
            'dname'    => '',
            'reason'   => 'wallhack',
            'fromsub'  => 0,
        ]);
        $this->assertTrue($first['ok']);

        $second = $this->api('bans.add', [
            'nickname' => 'CheaterAgain',
            'type'     => 0,
            'steam'    => 'STEAM_0:1:88888',
            'ip'       => '',
            'length'   => 0,
            'dfile'    => '',
            'dname'    => '',
            'reason'   => 'wallhack v2',
            'fromsub'  => 0,
        ]);
        $this->assertEnvelopeError($second, 'already_banned');
    }
}
