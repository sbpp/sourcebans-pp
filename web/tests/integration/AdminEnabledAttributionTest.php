<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Auth\Handler\NormalAuthHandler;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * #1509 — soft-retire (`admins.enabled`) + durable ban issuer names.
 */
final class AdminEnabledAttributionTest extends ApiTestCase
{
    public function testBansAddWritesAdminNameSnapshot(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.add', [
            'nickname' => 'Attributed',
            'type'     => 0,
            'steam'    => 'STEAM_0:0:15090',
            'ip'       => '',
            'length'   => 0,
            'dfile'    => '',
            'dname'    => '',
            'reason'   => 'snapshot check',
            'fromsub'  => 0,
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $bid = (int) ($env['data']['bid'] ?? 0);
        $this->assertGreaterThan(0, $bid);
        $ban = $this->row('bans', ['bid' => $bid]);
        $this->assertNotNull($ban);
        $this->assertSame('admin', $ban['admin_name']);
    }

    public function testDeactivateKeepsBanAdminNameViaJoinFallback(): void
    {
        $this->loginAsAdmin();
        $add = $this->api('admins.add', [
            'mask'            => 0,
            'srv_mask'        => '',
            'name'            => 'SoftIssuer',
            'steam'           => 'STEAM_0:0:15095',
            'email'           => 'soft@issuer.test',
            'password'        => 'longpassword',
            'password2'       => 'longpassword',
            'server_group'    => 'c',
            'web_group'       => 'c',
            'server_password' => '-1',
            'web_name'        => '',
            'server_name'     => '0',
            'servers'         => '',
            'single_servers'  => '',
        ]);
        $this->assertTrue($add['ok'], json_encode($add));
        $aid = (int) $add['data']['aid'];

        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp, admin_name)
             VALUES (UNIX_TIMESTAMP(), 0, "", ?, ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1", "")',
            DB_PREFIX
        ))->execute(['STEAM_0:1:15095', 'Player', 'keep name', $aid]);

        $this->assertTrue($this->api('admins.deactivate', ['aid' => $aid])['ok']);

        $stmt = $pdo->query(sprintf(
            "SELECT COALESCE(NULLIF(BA.admin_name, ''), AD.user) AS shown
             FROM `%s_bans` BA
             LEFT JOIN `%s_admins` AD ON BA.aid = AD.aid
             WHERE BA.authid = %s
             ORDER BY BA.bid DESC LIMIT 1",
            DB_PREFIX,
            DB_PREFIX,
            $pdo->quote('STEAM_0:1:15095'),
        ));
        $shown = $stmt->fetchColumn();
        $this->assertSame('SoftIssuer', $shown);
    }

    public function testHardRemovePreservesAdminNameSnapshot(): void
    {
        $this->loginAsAdmin();
        $add = $this->api('admins.add', [
            'mask'            => 0,
            'srv_mask'        => '',
            'name'            => 'HardIssuer',
            'steam'           => 'STEAM_0:0:15096',
            'email'           => 'hard@issuer.test',
            'password'        => 'longpassword',
            'password2'       => 'longpassword',
            'server_group'    => 'c',
            'web_group'       => 'c',
            'server_password' => '-1',
            'web_name'        => '',
            'server_name'     => '0',
            'servers'         => '',
            'single_servers'  => '',
        ]);
        $this->assertTrue($add['ok'], json_encode($add));
        $aid = (int) $add['data']['aid'];

        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp, admin_name)
             VALUES (UNIX_TIMESTAMP(), 0, "", ?, ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1", "")',
            DB_PREFIX
        ))->execute(['STEAM_0:1:15096', 'Player', 'snap on delete', $aid]);
        $bid = (int) $pdo->lastInsertId();

        $this->assertTrue($this->api('admins.remove', ['aid' => $aid])['ok']);

        $ban = $this->row('bans', ['bid' => $bid]);
        $this->assertNotNull($ban);
        $this->assertSame('HardIssuer', $ban['admin_name']);

        $stmt = $pdo->query(sprintf(
            "SELECT COALESCE(NULLIF(BA.admin_name, ''), AD.user) AS shown
             FROM `%s_bans` BA
             LEFT JOIN `%s_admins` AD ON BA.aid = AD.aid
             WHERE BA.bid = %d",
            DB_PREFIX,
            DB_PREFIX,
            $bid,
        ));
        $this->assertSame('HardIssuer', $stmt->fetchColumn());
    }

    public function testInactiveAdminFailsPasswordLogin(): void
    {
        $this->loginAsAdmin();
        $add = $this->api('admins.add', [
            'mask'            => 0,
            'srv_mask'        => '',
            'name'            => 'NoLogin',
            'steam'           => 'STEAM_0:0:15097',
            'email'           => 'nologin@test',
            'password'        => 'longpassword',
            'password2'       => 'longpassword',
            'server_group'    => 'c',
            'web_group'       => 'c',
            'server_password' => '-1',
            'web_name'        => '',
            'server_name'     => '0',
            'servers'         => '',
            'single_servers'  => '',
        ]);
        $this->assertTrue($add['ok'], json_encode($add));
        $aid = (int) $add['data']['aid'];
        $this->assertTrue($this->api('admins.deactivate', ['aid' => $aid])['ok']);

        $handler = new NormalAuthHandler($GLOBALS['PDO'], 'NoLogin', 'longpassword', false);
        $this->assertFalse($handler->getResult(), 'Inactive admin must fail closed like a bad password');
    }

    public function testInactiveAdminProfileLoadsForListButGrantsNoAccess(): void
    {
        $this->loginAsAdmin();
        $add = $this->api('admins.add', [
            'mask'            => ADMIN_LIST_ADMINS,
            'srv_mask'        => 'a',
            'name'            => 'ListInactive',
            'steam'           => 'STEAM_0:0:15098',
            'email'           => 'listinactive@test',
            'password'        => 'longpassword',
            'password2'       => 'longpassword',
            'server_group'    => 'c',
            'web_group'       => 'c',
            'server_password' => '-1',
            'web_name'        => '',
            'server_name'     => '0',
            'servers'         => '',
            'single_servers'  => '',
        ]);
        $this->assertTrue($add['ok'], json_encode($add));
        $aid = (int) $add['data']['aid'];
        $this->assertTrue($this->api('admins.deactivate', ['aid' => $aid])['ok']);

        /** @var \CUserManager $userbank */
        $userbank = $GLOBALS['userbank'];
        $flags = $userbank->GetProperty('srv_flags', $aid);
        $this->assertIsString($flags);
        $this->assertNotFalse(SmFlagsToSb((string) $flags));
        $this->assertFalse(
            $userbank->HasAccess(ADMIN_LIST_ADMINS, $aid),
            'Soft-retired admin must not pass HasAccess',
        );
    }

    public function testPluginAdminLoadSqlFiltersEnabled(): void
    {
        $path = dirname(__DIR__, 3) . '/game/addons/sourcemod/scripting/sbpp_main.sp';
        if (!is_file($path)) {
            $this->markTestSkipped('game/ is not mounted in the web container');
        }
        $src = file_get_contents($path);
        $this->assertIsString($src);
        $this->assertStringContainsString(
            'a.enabled = 1 AND',
            $src,
            'SourceMod admin-load query must refuse soft-retired admins',
        );
    }

    public function testUpdater811BackfillsAdminName(): void
    {
        $path = dirname(__DIR__, 3) . '/web/updater/data/811.php';
        $this->assertFileExists($path);
        $src = file_get_contents($path);
        $this->assertIsString($src);
        $this->assertStringContainsString('admin_name', $src);
        $this->assertStringContainsString('enabled', $src);
        $this->assertStringContainsString('SET BA.admin_name = AD.user', $src);
        $this->assertStringContainsString('SET CO.admin_name = AD.user', $src);
    }
}
