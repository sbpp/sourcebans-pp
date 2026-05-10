<?php

namespace Sbpp\Tests\Api;

use Sbpp\Servers\SourceQueryCache;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Per-handler coverage for web/api/handlers/servers.php. The full
 * add+remove round-trip lives in tests/integration/ServerCrudTest;
 * this file pins validation paths, the read-mostly host-info handlers
 * (which we cover at the "no-server-found" / "rcon-not-configured"
 * level), and the per-server send_rcon access check.
 */
final class ServersTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset the per-(ip, port) UDP query cache between tests so the
        // negative-cache entry from one test doesn't bleed into another
        // (#1311 — `Sbpp\Servers\SourceQueryCache` writes both success
        // and failure results to the on-disk cache).
        $cacheDir = SB_CACHE . 'srvquery/';
        if (is_dir($cacheDir)) {
            foreach (scandir($cacheDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                @unlink($cacheDir . $entry);
            }
        }
        SourceQueryCache::resetSocketAttemptCount();
        SourceQueryCache::setProbeOverrideForTesting(null);
    }

    protected function tearDown(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(null);
        parent::tearDown();
    }

    private function seedServer(int $sid = 1, string $rcon = ''): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (sid, ip, port, rcon, modid, enabled)
             VALUES (?, ?, ?, ?, 1, 1)',
            DB_PREFIX
        ))->execute([$sid, '203.0.113.1', 27015, $rcon]);
        return $sid;
    }

    public function testAddRejectsAnonymous(): void
    {
        $env = $this->api('servers.add', ['ip' => '1.1.1.1', 'port' => '27015', 'mod' => 1, 'group' => '0']);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('servers/add_forbidden', $env);
    }

    public function testAddSuccessSnapshot(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', [
            'ip'      => '10.0.0.2',
            'port'    => '27015',
            'rcon'    => 'r1',
            'rcon2'   => 'r1',
            'mod'     => 1,
            'enabled' => true,
            'group'   => '0',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSnapshot('servers/add_success', $env, ['data.sid']);
    }

    public function testAddValidatesIp(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', ['ip' => '', 'port' => '27015', 'mod' => 1, 'group' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('address', $env['error']['field']);
        $this->assertSnapshot('servers/add_validation_address', $env);
    }

    public function testAddValidatesIpFormat(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', ['ip' => 'not.an.ip', 'port' => '27015', 'mod' => 1, 'group' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('address', $env['error']['field']);
    }

    public function testAddValidatesPort(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', ['ip' => '1.1.1.1', 'port' => 'notanumber', 'mod' => 1, 'group' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('port', $env['error']['field']);
    }

    public function testAddRequiresMatchingRconConfirmation(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', [
            'ip' => '1.1.1.1', 'port' => '27015',
            'rcon' => 'a', 'rcon2' => 'b', 'mod' => 1, 'group' => '0',
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('rcon2', $env['error']['field']);
    }

    public function testAddRequiresModSelection(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', ['ip' => '1.1.1.1', 'port' => '27015', 'mod' => -2, 'group' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('mod', $env['error']['field']);
    }

    public function testAddRefusesDuplicateIpPort(): void
    {
        $this->loginAsAdmin();
        $params = [
            'ip' => '10.20.30.40', 'port' => '27015',
            'rcon' => '', 'rcon2' => '', 'mod' => 1, 'group' => '0',
        ];
        $first = $this->api('servers.add', $params);
        $this->assertTrue($first['ok'], json_encode($first));
        $env = $this->api('servers.add', $params);
        $this->assertEnvelopeError($env, 'duplicate');
        $this->assertSnapshot('servers/add_duplicate', $env);
    }

    public function testRemoveDeletesRow(): void
    {
        $this->loginAsAdmin();
        $sid = $this->seedServer();
        $env = $this->api('servers.remove', ['sid' => $sid]);
        $this->assertTrue($env['ok']);
        $this->assertNull($this->row('servers', ['sid' => $sid]));
        $this->assertSnapshot('servers/remove_success', $env, [
            'data.remove',
            'data.counter.srvcount',
        ]);
    }

    public function testRemoveRejectsAnonymous(): void
    {
        $env = $this->api('servers.remove', ['sid' => 1]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    /**
     * `servers.setup_edit` selects `gid` from `:prefix_servers`, but the
     * `servers` table has no `gid` column (servers→groups membership lives
     * in `:prefix_servers_groups`). The query throws a 1054 PDOException
     * the moment the handler is invoked, so we cannot snapshot a working
     * envelope.
     *
     * Tested here only at the dispatcher level — the perm matrix already
     * pins the (perm, requireAdmin, public) triple. A behavioural test of
     * the happy path will be added once the column-vs-join mismatch is
     * fixed in the handler (out of scope for this PR — it is a latent bug
     * that predates the JSON-API migration). The dispatcher-level reject
     * path is also covered by `PermissionMatrixTest::testRegistered…`.
     */
    public function testSetupEditRejectsAnonymous(): void
    {
        $env = $this->api('servers.setup_edit', ['sid' => 1]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testRefreshIsPublicEcho(): void
    {
        // Public read-mostly handler — just echoes the requested sid.
        $env = $this->api('servers.refresh', ['sid' => 12]);
        $this->assertTrue($env['ok']);
        $this->assertSame(12, (int)$env['data']['sid']);
        $this->assertSnapshot('servers/refresh_success', $env);
    }

    public function testHostPlayersRejectsUnknownServer(): void
    {
        // No row matches → handler throws not_found before touching sockets.
        $env = $this->api('servers.host_players', ['sid' => 9999]);
        $this->assertEnvelopeError($env, 'not_found');
        $this->assertSnapshot('servers/host_players_not_found', $env);
    }

    public function testHostPlayersReturnsConnectErrorForKnownServer(): void
    {
        // Known row but no live process → SourceQuery::Connect throws,
        // handler returns the structured "connect" error envelope.
        $this->seedServer(33);
        $env = $this->api('servers.host_players', ['sid' => 33]);
        $this->assertTrue($env['ok']);
        $this->assertSame('connect', $env['data']['error']);
        $this->assertSame(33, (int)$env['data']['sid']);
        $this->assertSnapshot('servers/host_players_connect_error', $env);
    }

    public function testHostPropertyRejectsUnknownServer(): void
    {
        $env = $this->api('servers.host_property', ['sid' => 9999]);
        $this->assertEnvelopeError($env, 'not_found');
    }

    public function testHostPropertyReturnsConnectErrorForKnownServer(): void
    {
        $this->seedServer(44);
        $env = $this->api('servers.host_property', ['sid' => 44]);
        $this->assertTrue($env['ok']);
        $this->assertSame('connect', $env['data']['error']);
    }

    /**
     * #1311 regression — back-to-back `host_players` calls against the
     * same `:prefix_servers` row must coalesce into a single A2S probe.
     * The probe override stands in for the live UDP path so the
     * assertion is deterministic; the matching cache-shape coverage
     * lives in `web/tests/integration/SourceQueryCacheTest.php`.
     */
    public function testHostPlayersCoalescesRapidRepeatCallsViaCache(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(static function (): array {
            return [
                'info' => [
                    'HostName'   => 'Cached HL',
                    'Players'    => 7,
                    'MaxPlayers' => 24,
                    'Map'        => 'cp_dustbowl',
                    'Os'         => 'l',
                    'Secure'     => true,
                ],
                'players' => [
                    ['Id' => 0, 'Name' => 'foo', 'Frags' => 9, 'Time' => 600, 'TimeF' => '10:00'],
                ],
            ];
        });

        $sid = $this->seedServer(101);

        for ($i = 0; $i < 5; $i++) {
            $env = $this->api('servers.host_players', ['sid' => $sid]);
            $this->assertTrue($env['ok'], "iteration $i envelope: " . json_encode($env));
            $this->assertSame('Cached HL',   $env['data']['hostname']);
            $this->assertSame(7,             $env['data']['players']);
            $this->assertSame('cp_dustbowl', $env['data']['map']);
        }

        $this->assertSame(
            1,
            SourceQueryCache::socketAttemptCount(),
            '5 rapid host_players calls must hit the cache after the first; #1311 amplifier reopened otherwise',
        );
    }

    /**
     * #1311 regression — a `host_players` call against an unreachable
     * server must NOT keep hammering the socket. Negative caching
     * stamps the failed probe into the same `(ip, port)` slot so the
     * second call returns the structured `connect` envelope without
     * touching UDP again.
     */
    public function testHostPlayersNegativeCachesUnreachableServers(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(static fn(): ?array => null);
        $sid = $this->seedServer(102);

        for ($i = 0; $i < 5; $i++) {
            $env = $this->api('servers.host_players', ['sid' => $sid]);
            $this->assertTrue($env['ok']);
            $this->assertSame('connect', $env['data']['error']);
        }

        $this->assertSame(
            1,
            SourceQueryCache::socketAttemptCount(),
            'unreachable servers must be negative-cached so an attacker mashing the refresh button costs ONE probe per window',
        );
    }

    public function testHostPlayersListReturnsEmptyForNoIds(): void
    {
        $env = $this->api('servers.host_players_list', ['sids' => '']);
        $this->assertTrue($env['ok']);
        $this->assertSame([], $env['data']['lines']);
        $this->assertSnapshot('servers/host_players_list_empty', $env);
    }

    public function testHostPlayersListReturnsErrorRowForKnownServer(): void
    {
        $this->seedServer(55);
        $env = $this->api('servers.host_players_list', ['sids' => '55;']);
        $this->assertTrue($env['ok']);
        $this->assertCount(1, $env['data']['lines']);
        $this->assertStringStartsWith('ERROR ', $env['data']['lines'][0]);
    }

    public function testPlayersReturnsEmptyForUnknownServer(): void
    {
        $env = $this->api('servers.players', ['sid' => 9999]);
        $this->assertTrue($env['ok']);
        $this->assertSame(9999, (int)$env['data']['sid']);
        $this->assertSame([], $env['data']['players']);
        $this->assertSnapshot('servers/players_unknown_sid', $env);
    }

    public function testSendRconRejectsAnonymous(): void
    {
        $env = $this->api('servers.send_rcon', ['sid' => 1, 'command' => 'status']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testSendRconRejectsAdminWithoutPerServerAccess(): void
    {
        // Admin holds SM_ROOT (`z`) globally (Fixture::seedAdmin sets
        // extraflags=ADMIN_OWNER, but srv_flags is ''). Add 'z' so the
        // dispatcher's perm check (SM_RCON.SM_ROOT) lets the call through;
        // the per-server check inside the handler still says no.
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);

        $this->loginAsAdmin();
        $this->seedServer(11);

        $env = $this->api('servers.send_rcon', ['sid' => 11, 'command' => 'status']);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('servers/send_rcon_forbidden_per_server', $env);
    }

    public function testSendRconBlocksRconPasswordSpoof(): void
    {
        // Set the admin up with global SM rcon and per-server access.
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        $sid = $this->seedServer(22);
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $this->loginAsAdmin();

        // The rcon_password command (and HTML-encoded variants) must short-
        // circuit before reaching the gameserver — otherwise an admin could
        // exfiltrate the saved password.
        $env = $this->api('servers.send_rcon', ['sid' => $sid, 'command' => 'rcon&#95;password']);
        $this->assertTrue($env['ok']);
        $this->assertSame('error', $env['data']['kind']);
        $this->assertStringContainsString("Don't try to cheat", $env['data']['error']);
        $this->assertSnapshot('servers/send_rcon_password_blocked', $env);
    }

    public function testSendRconNoopForEmptyCommand(): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        $sid = $this->seedServer(66);
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $this->loginAsAdmin();
        $env = $this->api('servers.send_rcon', ['sid' => $sid, 'command' => '']);
        $this->assertTrue($env['ok']);
        $this->assertSame('noop', $env['data']['kind']);
    }

    public function testSendRconClearReturnsClearKind(): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        $sid = $this->seedServer(77);
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $this->loginAsAdmin();
        $env = $this->api('servers.send_rcon', ['sid' => $sid, 'command' => 'clr']);
        $this->assertTrue($env['ok']);
        $this->assertSame('clear', $env['data']['kind']);
    }
}
