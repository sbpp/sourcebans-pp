<?php

namespace Sbpp\Tests\Api;

use Sbpp\Servers\RconStatusCache;
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

        // Mirror the SourceQueryCache reset for the sibling
        // RconStatusCache (the SteamID side-channel of
        // `api_servers_host_players` reads off it; the negative-cache
        // entry from one test would otherwise bleed into the next).
        $rconCacheDir = SB_CACHE . 'srvstatus/';
        if (is_dir($rconCacheDir)) {
            foreach (scandir($rconCacheDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                @unlink($rconCacheDir . $entry);
            }
        }
        RconStatusCache::resetSocketAttemptCount();
        RconStatusCache::setProbeOverrideForTesting(null);
    }

    protected function tearDown(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(null);
        RconStatusCache::setProbeOverrideForTesting(null);
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

    public function testAddRejectsWhitespaceInAddress(): void
    {
        // Post-#1433 the address validator accepts EITHER a valid IP
        // OR a valid hostname (per `FILTER_VALIDATE_DOMAIN +
        // FILTER_FLAG_HOSTNAME`) so the help text's "IPv4 / IPv6 /
        // hostname" claim actually holds. Embedded whitespace is one
        // shape BOTH filters reject — pin it as a representative
        // garbage-input case. The contract: validation MUST reject
        // values neither filter accepts.
        //
        // Note: PHP's `FILTER_FLAG_HOSTNAME` accepts numeric-only
        // labels (`999.999.999.999` is a "valid hostname" per the
        // RFC 1035 label grammar — `4.3.2.1.in-addr.arpa` is the
        // canonical PTR shape), so the IP-rejection contract can't
        // be tested by feeding "obviously wrong" IPs and expecting
        // the validator to surface them as IP errors specifically.
        // The IP path is instead pinned by `testAddAcceptsBareIPv6`
        // — the `:` characters in `2606:4700:4700::1111` are
        // rejected by `FILTER_FLAG_HOSTNAME`, so that input can
        // ONLY round-trip via the `FILTER_VALIDATE_IP` arm.
        $this->loginAsAdmin();
        $env = $this->api('servers.add', ['ip' => 'has spaces in it', 'port' => '27015', 'mod' => 1, 'group' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('address', $env['error']['field']);
    }

    public function testAddAcceptsHostname(): void
    {
        // #1433 — the form template advertises "IPv4 / IPv6 /
        // hostname" support and the sibling `admin.edit.server.php`
        // already accepts hostnames; this pins the JSON dispatcher
        // side onto the same contract.
        $this->loginAsAdmin();
        $env = $this->api('servers.add', [
            'ip'    => 'cs.example.com',
            'port'  => '27015',
            'rcon'  => '',
            'rcon2' => '',
            'mod'   => 1,
            'group' => '0',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $row = $this->row('servers', ['sid' => $env['data']['sid']]);
        $this->assertNotNull($row);
        $this->assertSame('cs.example.com', $row['ip']);
        $this->assertSame(27015, (int) $row['port']);
    }

    public function testAddAcceptsFqdn(): void
    {
        // Multi-label FQDN — the reporter's marquee shape on #1433.
        $this->loginAsAdmin();
        $env = $this->api('servers.add', [
            'ip'    => 'gameserver.eu.example.com',
            'port'  => '27015',
            'rcon'  => '',
            'rcon2' => '',
            'mod'   => 1,
            'group' => '0',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $row = $this->row('servers', ['sid' => $env['data']['sid']]);
        $this->assertNotNull($row);
        $this->assertSame('gameserver.eu.example.com', $row['ip']);
        // Pin the port column too — the snapshot suite already
        // covers IPv4-route happy paths but #1433 is the first
        // hostname-shaped row through the writer; a future refactor
        // that incorrectly typed-coerces `port` against the
        // hostname-bearing arm would slip past the IP-bearing tests.
        $this->assertSame(27015, (int) $row['port']);
    }

    public function testAddAcceptsBareIPv6(): void
    {
        // FILTER_VALIDATE_IP accepts both v4 and v6; FILTER_FLAG_NO_PRIV_RANGE
        // / NO_RES_RANGE are NOT set, so private+reserved blocks are
        // fine for self-hosters running game servers inside their LAN.
        // Pin a public IPv6 (Cloudflare DNS) to keep the shape simple.
        // The `:port` column is separate so no `[v6]:port` parsing is
        // needed at this layer.
        $this->loginAsAdmin();
        $env = $this->api('servers.add', [
            'ip'    => '2606:4700:4700::1111',
            'port'  => '27015',
            'rcon'  => '',
            'rcon2' => '',
            'mod'   => 1,
            'group' => '0',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $row = $this->row('servers', ['sid' => $env['data']['sid']]);
        $this->assertNotNull($row);
        $this->assertSame('2606:4700:4700::1111', $row['ip']);
    }

    public function testAddRejectsAddressExceedingSchemaWidth(): void
    {
        // `:prefix_servers.ip` is `VARCHAR(64) NOT NULL`. Pre-#1433
        // the implicit cap was "IPv4 fits, hostnames don't exist
        // here" — `FILTER_VALIDATE_IP` rejected everything over 39
        // chars (the IPv6 max). Post-#1433 hostnames up to RFC 1035's
        // 253-char max pass `FILTER_VALIDATE_DOMAIN |
        // FILTER_FLAG_HOSTNAME`, but the column would truncate
        // anything >64 and MariaDB's `STRICT_TRANS_TABLES` mode
        // (the default) would raise `SQLSTATE[22001]` — surfacing
        // as a generic `server_error` 500 with no audit-log entry.
        // The handler-side gate translates that into an actionable
        // `validation` envelope with the same shape the empty-input
        // / bad-format branches use.
        $this->loginAsAdmin();
        // 64 chars at a glance: 13 chars × 5 = 65; subtract one for
        // the trailing `.com` (4 chars) and add a single leading
        // `a` to land on 65 chars — one over the cap.
        $long = str_repeat('a', 61) . '.com'; // 65 chars total
        $this->assertGreaterThan(64, strlen($long));
        $env = $this->api('servers.add', [
            'ip'    => $long,
            'port'  => '27015',
            'rcon'  => '',
            'rcon2' => '',
            'mod'   => 1,
            'group' => '0',
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('address', $env['error']['field']);
        $this->assertStringContainsString('64', $env['error']['message'],
            'validation copy must surface the 64-char cap so the operator knows what to trim',
        );
    }

    public function testAddRefusesDuplicateHostnamePort(): void
    {
        // #1433 follow-up — the duplicate-detection branch
        // (the existing `INSERT IGNORE`-style guard further down
        // in `api_servers_add`) keyed off `(ip, port)` pre-#1433
        // when only IPs landed in that column. Hostnames travel
        // through the same code path and must collide on identical
        // `(hostname, port)` too. Mirror of
        // `testAddRefusesDuplicateIpPort`.
        $this->loginAsAdmin();
        $params = [
            'ip' => 'cs2.example.com', 'port' => '27015',
            'rcon' => '', 'rcon2' => '', 'mod' => 1, 'group' => '0',
        ];
        $first = $this->api('servers.add', $params);
        $this->assertTrue($first['ok'], json_encode($first));
        $env = $this->api('servers.add', $params);
        $this->assertEnvelopeError($env, 'duplicate');
    }

    public function testAddRejectsGarbageAddress(): void
    {
        // Belt-and-suspenders — make sure obvious garbage
        // (special chars / whitespace / shell-meta) is still
        // rejected after the hostname pathway opens up.
        $this->loginAsAdmin();
        $env = $this->api('servers.add', [
            'ip'    => 'not a valid hostname or ip',
            'port'  => '27015',
            'rcon'  => '',
            'rcon2' => '',
            'mod'   => 1,
            'group' => '0',
        ]);
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
     * #1487 — a `trunchostname` of `0` is the "no server-side
     * truncation" sentinel: the handler returns the full hostname so
     * the dashboard widget's CSS `.truncate` can do the responsive
     * visual cut. Pre-fix the dashboard forwarded a fixed `40`-char
     * cap that chopped the name server-side before the browser ever
     * saw it; now it forwards `0` and the row shows as much of the
     * name as fits in its column.
     */
    public function testHostPlayersReturnsFullHostnameWhenTrunchostnameZero(): void
    {
        $longHost = 'SourceBans++ Community | 24/7 FastDL | Dust2 Only | EU West';
        $this->assertGreaterThan(48, strlen($longHost), 'guard: the fixture hostname must exceed the legacy 48-char default to make the assertion meaningful');

        SourceQueryCache::setProbeOverrideForTesting(static function () use ($longHost): array {
            return [
                'info' => [
                    'HostName'   => $longHost,
                    'Players'    => 3,
                    'MaxPlayers' => 24,
                    'Map'        => 'de_dust2',
                    'Os'         => 'l',
                    'Secure'     => true,
                ],
                'players' => [],
            ];
        });

        $sid = $this->seedServer(130);
        $env = $this->api('servers.host_players', ['sid' => $sid, 'trunchostname' => 0]);

        $this->assertTrue($env['ok'], 'envelope: ' . json_encode($env));
        $this->assertSame(
            $longHost,
            $env['data']['hostname'],
            'trunchostname=0 must return the hostname verbatim (no "..."); CSS handles the visual cut client-side (#1487)',
        );
        $this->assertStringNotContainsString('...', $env['data']['hostname']);
    }

    /**
     * #1487 — the inverse contract: a positive `trunchostname` still
     * truncates server-side. The dashboard opts out via `0`, but every
     * other surface (public list `70`, Add Admin grid / Server Groups
     * `40`) relies on the cap staying live, so the positive branch must
     * keep working.
     */
    public function testHostPlayersTruncatesHostnameWhenTrunchostnamePositive(): void
    {
        $longHost = 'SourceBans++ Community | 24/7 FastDL | Dust2 Only | EU West';

        SourceQueryCache::setProbeOverrideForTesting(static function () use ($longHost): array {
            return [
                'info' => [
                    'HostName'   => $longHost,
                    'Players'    => 3,
                    'MaxPlayers' => 24,
                    'Map'        => 'de_dust2',
                    'Os'         => 'l',
                    'Secure'     => true,
                ],
                'players' => [],
            ];
        });

        $sid = $this->seedServer(131);
        $env = $this->api('servers.host_players', ['sid' => $sid, 'trunchostname' => 10]);

        $this->assertTrue($env['ok'], 'envelope: ' . json_encode($env));
        $this->assertSame(
            substr($longHost, 0, 10) . '...',
            $env['data']['hostname'],
            'a positive trunchostname must still cap the hostname server-side (#1487 keeps the positive branch intact for the non-dashboard surfaces)',
        );
    }

    /**
     * #1487 — the property handler shares the same `trunc()` call, so
     * the `0` sentinel must disable truncation there too. Pinned so a
     * future refactor that splits the two handlers' truncation paths
     * can't silently re-cap one of them.
     */
    public function testHostPropertyReturnsFullHostnameWhenTrunchostnameZero(): void
    {
        $longHost = 'SourceBans++ Community | 24/7 FastDL | Dust2 Only | EU West';

        SourceQueryCache::setProbeOverrideForTesting(static function () use ($longHost): array {
            return [
                'info' => [
                    'HostName'   => $longHost,
                    'Players'    => 0,
                    'MaxPlayers' => 24,
                    'Map'        => 'de_dust2',
                    'Os'         => 'l',
                    'Secure'     => true,
                ],
                'players' => [],
            ];
        });

        $sid = $this->seedServer(132);
        $env = $this->api('servers.host_property', ['sid' => $sid, 'trunchostname' => 0]);

        $this->assertTrue($env['ok'], 'envelope: ' . json_encode($env));
        $this->assertSame($longHost, $env['data']['hostname']);
        $this->assertStringNotContainsString('...', $env['data']['hostname']);
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

    /**
     * Player-context-menu restoration — admins with
     * `ADMIN_OWNER | ADMIN_ADD_BAN` AND per-server RCON access must
     * receive per-player SteamIDs in the `player_list` response. The
     * SteamID side-channel is the load-bearing data the right-click
     * menu reads off; the JS feature-detects the `steamid` field on
     * each row and skips the menu wiring on rows that don't carry it.
     *
     * Setup mirrors the existing per-server RCON tests (admin flag
     * `'mz'` + an `admins_servers_groups` row pinning the admin to
     * the seeded server). Both the SourceQuery and RCON probes are
     * driven by their test-only overrides so the assertion never
     * touches a real socket.
     */
    public function testHostPlayersIncludesSteamIDsForAdminWithRconAccess(): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        $sid = $this->seedServer(200, 'r1');
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $this->loginAsAdmin();

        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info' => [
                'HostName'   => 'Alpha Server',
                'Players'    => 2,
                'MaxPlayers' => 24,
                'Map'        => 'cp_dustbowl',
                'Os'         => 'l',
                'Secure'     => true,
            ],
            'players' => [
                ['Id' => 0, 'Name' => 'Alice', 'Frags' => 12, 'Time' => 1200, 'TimeF' => '20:00'],
                ['Id' => 1, 'Name' => 'Bob',   'Frags' => 7,  'Time' => 400,  'TimeF' => '06:40'],
            ],
        ]);
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => 'Alice', 'steamid' => 'STEAM_0:0:1234', 'ip' => '203.0.113.10'],
            ['id' => 2, 'name' => 'Bob',   'steamid' => '[U:1:2468]',    'ip' => '203.0.113.11'],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertTrue($env['data']['can_ban_player'], 'admin with per-server RCON access must get can_ban_player=true');
        $list = $env['data']['player_list'];
        $this->assertCount(2, $list);
        $this->assertSame('STEAM_0:0:1234', $list[0]['steamid']);
        $this->assertSame('[U:1:2468]',     $list[1]['steamid']);
    }

    public function testHostPlayersOmitsSteamIDsForAnonymousCaller(): void
    {
        // Anonymous caller — Fixture::reset() in setUp leaves
        // $userbank unauthenticated.
        $sid = $this->seedServer(201, 'r1');

        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info'    => ['HostName' => 'Anon Server', 'Players' => 1, 'MaxPlayers' => 24, 'Map' => 'pl_upward', 'Os' => 'l', 'Secure' => false],
            'players' => [['Id' => 0, 'Name' => 'Charlie', 'Frags' => 0, 'Time' => 60, 'TimeF' => '01:00']],
        ]);
        // Probe override is set even though the handler should never
        // reach the RCON cache — if the gate is broken and the probe
        // DOES fire we want to fail loudly via the socketAttemptCount
        // assertion below, not silently fall through to a real RCON.
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => 'Charlie', 'steamid' => 'STEAM_0:0:99', 'ip' => '198.51.100.20'],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok']);
        $this->assertFalse($env['data']['can_ban_player'], 'anonymous caller must not get can_ban_player=true');
        $list = $env['data']['player_list'];
        $this->assertCount(1, $list);
        $this->assertArrayNotHasKey('steamid', $list[0],
            'anonymous caller must NOT receive per-player SteamIDs — that is the load-bearing gate for the context menu',
        );
        $this->assertSame(0, RconStatusCache::socketAttemptCount(),
            'the RCON cache probe must not even fire for anonymous callers — the gate is upstream of the cache',
        );
    }

    public function testHostPlayersOmitsSteamIDsWhenAdminLacksRconAccessToThisServer(): void
    {
        // Logged-in admin holds ADMIN_OWNER | ADMIN_ADD_BAN (the
        // base seeded admin row carries `extraflags=16777216` which
        // is ADMIN_OWNER), but has NO per-server mapping for the
        // seeded `sid` (no row in `_admins_servers_groups`). The
        // SteamID surfacing must skip them — the kick/ban URLs the
        // menu points at would 403 on the per-server RCON check
        // anyway.
        $this->loginAsAdmin();
        $sid = $this->seedServer(202, 'r1');

        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info'    => ['HostName' => 'No-Rcon Server', 'Players' => 1, 'MaxPlayers' => 24, 'Map' => 'cp_badlands', 'Os' => 'l', 'Secure' => true],
            'players' => [['Id' => 0, 'Name' => 'Dave', 'Frags' => 3, 'Time' => 100, 'TimeF' => '01:40']],
        ]);
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => 'Dave', 'steamid' => 'STEAM_0:0:42', 'ip' => '203.0.113.42'],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok']);
        $this->assertFalse($env['data']['can_ban_player'],
            'admin without per-server RCON access must not get can_ban_player=true',
        );
        $list = $env['data']['player_list'];
        $this->assertArrayNotHasKey('steamid', $list[0],
            'admin without per-server RCON access must NOT receive SteamIDs — the gate is the per-server check, not the global flag',
        );
        $this->assertSame(0, RconStatusCache::socketAttemptCount(),
            'the RCON cache must not be probed when the per-server gate fails',
        );
    }

    public function testHostPlayersCanBanFlagStaysTrueEvenWithoutPerServerRcon(): void
    {
        // Sanity check that the pre-restoration `can_ban` flag —
        // which the ban-row affordance on the public list reads —
        // is NOT affected by the new per-server RCON gate. `can_ban`
        // is the global "this admin can add a ban somewhere" check;
        // `can_ban_player` is the new "this admin can ban THIS
        // player on THIS server" check. They diverge for admins
        // with the global flag but no per-server RCON access.
        $this->loginAsAdmin();
        $sid = $this->seedServer(203, 'r1');
        // No admins_servers_groups row -> no per-server RCON.

        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info'    => ['HostName' => 'Split-Gate Server', 'Players' => 0, 'MaxPlayers' => 24, 'Map' => 'pl_badwater', 'Os' => 'l', 'Secure' => true],
            'players' => [],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok']);
        $this->assertTrue($env['data']['can_ban'],
            'can_ban is the global flag-based gate — admins with ADMIN_ADD_BAN keep it regardless of per-server RCON',
        );
        $this->assertFalse($env['data']['can_ban_player'],
            'can_ban_player is the per-server gate — fails when admin lacks RCON to THIS sid',
        );
    }

    /**
     * Two players with the same name on one server can't be
     * disambiguated from A2S `GetPlayers` + RCON `status` alone.
     * Picking the first SteamID would mis-target a kick/ban —
     * the conservative shape is to drop BOTH rows from the
     * SteamID side-channel and let the affected admins reach
     * for the existing `?p=admin&c=kickit/blockit/bans` flows.
     * The right-click menu's JS gates on the presence of the
     * `steamid` field per row, so dropped rows naturally fall
     * back to the native browser context menu.
     *
     * The handler does NOT have to drop the row outright — the
     * `name` / `frags` / `time_f` fields are still useful for the
     * visible player list. Only the SteamID-driven menu wiring
     * is suppressed.
     */
    public function testHostPlayersDropsSteamIDOnDuplicateNamesInRconStatus(): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        $sid = $this->seedServer(210, 'r1');
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $this->loginAsAdmin();

        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info'    => ['HostName' => 'Dupe Server', 'Players' => 3, 'MaxPlayers' => 24, 'Map' => 'cp_dustbowl', 'Os' => 'l', 'Secure' => true],
            'players' => [
                ['Id' => 0, 'Name' => 'Alice',  'Frags' => 12, 'Time' => 1200, 'TimeF' => '20:00'],
                ['Id' => 1, 'Name' => 'Alice',  'Frags' => 5,  'Time' => 300,  'TimeF' => '05:00'],
                ['Id' => 2, 'Name' => 'Unique', 'Frags' => 8,  'Time' => 600,  'TimeF' => '10:00'],
            ],
        ]);
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => 'Alice',  'steamid' => 'STEAM_0:0:1111', 'ip' => '203.0.113.10'],
            ['id' => 2, 'name' => 'Alice',  'steamid' => 'STEAM_0:0:2222', 'ip' => '203.0.113.11'],
            ['id' => 3, 'name' => 'Unique', 'steamid' => 'STEAM_0:0:3333', 'ip' => '203.0.113.12'],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok']);
        $list = $env['data']['player_list'];
        $this->assertCount(3, $list);

        // Both 'Alice' rows must be missing the steamid field — the
        // RCON status had two distinct SteamIDs for that name and
        // there's no defensible mapping back to the A2S rows.
        $this->assertSame('Alice', $list[0]['name']);
        $this->assertArrayNotHasKey('steamid', $list[0],
            'duplicate-named A2S/RCON rows must NOT receive a SteamID — mis-attribution would mis-target a kick/ban',
        );
        $this->assertSame('Alice', $list[1]['name']);
        $this->assertArrayNotHasKey('steamid', $list[1],
            'both duplicate-named rows must be dropped from the side-channel, not just the second one',
        );

        // The non-colliding row still surfaces its SteamID — the
        // gate is per-name, not all-or-nothing.
        $this->assertSame('Unique', $list[2]['name']);
        $this->assertSame('STEAM_0:0:3333', $list[2]['steamid']);
    }

    public function testHostPlayersDropsSteamIDWhenSourceQueryHasDuplicateName(): void
    {
        // Inverse of the above: RCON has a single unique entry for
        // "Alice", but A2S returns two players named "Alice". The
        // handler can't tell which A2S row is the "real" Alice, so
        // the safe shape is to drop both.
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        $sid = $this->seedServer(211, 'r1');
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $this->loginAsAdmin();

        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info'    => ['HostName' => 'A2S Dupe Server', 'Players' => 2, 'MaxPlayers' => 24, 'Map' => 'cp_badlands', 'Os' => 'l', 'Secure' => true],
            'players' => [
                ['Id' => 0, 'Name' => 'Alice', 'Frags' => 12, 'Time' => 1200, 'TimeF' => '20:00'],
                ['Id' => 1, 'Name' => 'Alice', 'Frags' => 5,  'Time' => 300,  'TimeF' => '05:00'],
            ],
        ]);
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => 'Alice', 'steamid' => 'STEAM_0:0:1111', 'ip' => '203.0.113.10'],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok']);
        $list = $env['data']['player_list'];
        $this->assertCount(2, $list);
        $this->assertArrayNotHasKey('steamid', $list[0],
            'duplicate A2S name must drop the SteamID — picking arbitrarily would mis-target a kick/ban',
        );
        $this->assertArrayNotHasKey('steamid', $list[1]);
    }

    /**
     * The player name flows raw from RCON status output through the
     * JSON response — both the JS client (`renderPlayers` in
     * `server-tile-hydrate.js`, the `server-context-menu.js`
     * `data-name` / `aria-label` / `textContent` wiring) and the
     * server-side JSON serialisation handle it as untrusted. This
     * test pins the contract by stubbing a malicious name and
     * asserting it round-trips verbatim — defense-in-depth so a
     * future refactor that, e.g., concatenates the name into HTML
     * server-side fails the gate immediately.
     */
    public function testHostPlayersPreservesMaliciousPlayerNameVerbatim(): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        $sid = $this->seedServer(212, 'r1');
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $this->loginAsAdmin();

        $hostileName = '<img src=x onerror=alert(1)>';
        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info'    => ['HostName' => 'XSS Probe', 'Players' => 1, 'MaxPlayers' => 24, 'Map' => 'cp_dustbowl', 'Os' => 'l', 'Secure' => true],
            'players' => [['Id' => 0, 'Name' => '<img src=x onerror=alert(1)>', 'Frags' => 1, 'Time' => 60, 'TimeF' => '01:00']],
        ]);
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => '<img src=x onerror=alert(1)>', 'steamid' => 'STEAM_0:0:8675309', 'ip' => '203.0.113.20'],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok']);
        $list = $env['data']['player_list'];
        $this->assertCount(1, $list);
        $this->assertSame($hostileName, $list[0]['name'],
            'player name must round-trip raw — escaping is the JS layer\'s job (textContent / setAttribute). '
                . 'Server-side escape would silently double-encode through the JSON dispatcher.',
        );
        $this->assertSame('STEAM_0:0:8675309', $list[0]['steamid']);

        // Belt-and-braces: the raw JSON payload must NOT contain the
        // literal HTML — it must be JSON-escaped (e.g. `<` is
        // encoded as `\u003c`) so a panel viewer's browser never
        // parses the response body as HTML. This is the json_encode
        // contract; the assertion catches a regression where someone
        // swapped json_encode flags or wrapped the response in a
        // non-JSON content type.
        $json = json_encode($env['data']);
        $this->assertNotFalse($json);
        // `<` must NOT appear literally in the JSON envelope when
        // emitted with JSON_HEX_TAG (the panel uses this flag); we
        // assert the un-escaped tag stays out so a content-type
        // confusion downstream can't paint the response as HTML.
        // (The default json_encode call inside Api::reply emits
        // application/json so the browser does not parse it as HTML
        // regardless, but this is the defensive shape.)
        $this->assertStringContainsString('onerror=alert(1)', $json,
            'sanity check: the hostile name is in the response body',
        );
    }

    /**
     * #1396 — Some Source-engine variants and SourceMod plugins return
     * an A2S `GetPlayers` entry with an empty `Name` field (the
     * "host slot" / "console" stub). Pre-fix the handler passed
     * these through verbatim to `player_list`, so the client
     * rendered a phantom `<li data-testid="server-player">` with
     * an empty name span + a meta span like "0 · ". Two failure
     * modes:
     *
     *   1. The phantom row has no `steamid` (the matcher gate
     *      filters empty names out of `$steamidByName` and
     *      `$rconNameCount`), so `web/scripts/server-tile-hydrate.js`
     *      renderPlayers() also skips the `data-context-menu`
     *      hooks on it. Right-clicking the row does nothing.
     *   2. Visually the empty row is a thin border-bottom strip
     *      directly above the first named player; users perceive
     *      the next real player ("Fletcher" in the user's report)
     *      as "the first player of the list" — and when their
     *      right-click lands on the phantom row above instead of
     *      the named row, the menu silently fails to open.
     *
     * The fix: empty-name A2S entries are filtered out of
     * `$playerList` at the same gate that already skips them from
     * the steamid-by-name lookup (web/api/handlers/servers.php).
     * The contract: `player_list` only contains rows with a
     * non-empty `name`. Bots, real players whose A2S name didn't
     * match the RCON status output, and admin-without-rcon callers
     * all still render — the gate is strictly "name === ''".
     */
    public function testHostPlayersFiltersEmptyNameEntries(): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        $sid = $this->seedServer(220, 'r1');
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $this->loginAsAdmin();

        // A2S response with an empty-name entry first (the "host
        // slot" quirk) — index 1 onwards are real players the
        // user perceives as "the first / second / third player".
        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info'    => [
                'HostName'   => 'Phantom-Row Server',
                'Players'    => 3,
                'MaxPlayers' => 24,
                'Map'        => 'cp_dustbowl',
                'Os'         => 'l',
                'Secure'     => true,
            ],
            'players' => [
                ['Id' => 0, 'Name' => '',         'Frags' => 0,  'Time' => 0,    'TimeF' => '00:00'],
                ['Id' => 1, 'Name' => 'Fletcher', 'Frags' => 12, 'Time' => 1200, 'TimeF' => '20:00'],
                ['Id' => 2, 'Name' => 'kovka',    'Frags' => 7,  'Time' => 400,  'TimeF' => '06:40'],
            ],
        ]);
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => 'Fletcher', 'steamid' => 'STEAM_0:0:1234', 'ip' => '203.0.113.10'],
            ['id' => 2, 'name' => 'kovka',    'steamid' => 'STEAM_0:0:5678', 'ip' => '203.0.113.11'],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $list = $env['data']['player_list'];

        // The empty-name entry must NOT be in the response. The
        // pre-#1396 shape would carry 3 rows; the fix drops it to 2.
        $this->assertCount(2, $list,
            'empty-name A2S entries must be filtered out of player_list (#1396)',
        );
        // The visually-first player (Fletcher) is now at index 0
        // with the steamid the menu needs.
        $this->assertSame('Fletcher', $list[0]['name']);
        $this->assertSame('STEAM_0:0:1234', $list[0]['steamid']);
        $this->assertSame('kovka', $list[1]['name']);
        $this->assertSame('STEAM_0:0:5678', $list[1]['steamid']);
        // Defensive: no entry should have an empty name.
        foreach ($list as $row) {
            $this->assertNotSame('', $row['name'],
                'no player_list row should have an empty name post-#1396',
            );
        }
    }

    /**
     * #1396 — Defensive sibling of the test above. If the entire
     * A2S response is empty-name garbage, `player_list` should be
     * empty (the JS then renders its `[data-empty-message]`
     * placeholder instead of a wall of phantom rows). The
     * `players` count from the A2S info block stays as-is — that
     * field is the server-reported player count, not the rendered
     * list length, and the two can legitimately diverge (e.g. a
     * SourceMod plugin inflating the public count).
     */
    public function testHostPlayersFiltersAllEmptyNameEntries(): void
    {
        $sid = $this->seedServer(221, 'r1');
        SourceQueryCache::setProbeOverrideForTesting(static fn(): array => [
            'info'    => [
                'HostName'   => 'All-Empty Server',
                'Players'    => 3,
                'MaxPlayers' => 24,
                'Map'        => 'cp_dustbowl',
                'Os'         => 'l',
                'Secure'     => true,
            ],
            'players' => [
                ['Id' => 0, 'Name' => '', 'Frags' => 0, 'Time' => 0, 'TimeF' => '00:00'],
                ['Id' => 1, 'Name' => '', 'Frags' => 0, 'Time' => 0, 'TimeF' => '00:00'],
                ['Id' => 2, 'Name' => '', 'Frags' => 0, 'Time' => 0, 'TimeF' => '00:00'],
            ],
        ]);

        $env = $this->api('servers.host_players', ['sid' => $sid]);
        $this->assertTrue($env['ok']);
        $this->assertCount(0, $env['data']['player_list'],
            'all-empty-name A2S response must yield an empty player_list (#1396)',
        );
        $this->assertSame(3, $env['data']['players'],
            'A2S-reported player count is independent of the rendered list — the inflated count stays as-is',
        );
    }
}
