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

    /**
     * #1423 follow-up #4 — pre-fix `api_kickit_kick_player` called
     * `SteamID::compare($player_steamid, $check)` without gating
     * `$check` first. `compare()` routes through `toSteam64()` which
     * throws `Exception('Invalid SteamID input!')` on any input
     * that fails the library's strict shape gate. The exception
     * escaped the handler via `Api::handle`'s `Throwable` fallback as
     * a generic 500 envelope; the iframe loop then can't tell "no
     * match" apart from "your input was garbage", and a hostile
     * caller posting `?check=garbage&type=0` reliably 500'd the
     * panel. The fix is a pre-`SteamID::compare()` `isValidID()` /
     * `filter_var(IP)` gate that surfaces the structured `not_found`
     * envelope the iframe expects on any malformed input.
     */
    public function testKickPlayerReturnsNotFoundForMalformedSteamId(): void
    {
        $this->loginAsAdmin();
        foreach (
            [
                'garbage',                  // unrecognised shape
                'STEAM_0:0:',               // empty Z (library accepts loose `\d*` but `\d+` rejects)
                "STEAM_0:0:1\n",            // trailing newline (library `D` modifier rejects)
                'STEAM_2:0:1',              // X=2 (library `[01]` rejects)
                '',                          // empty
            ] as $badCheck
        ) {
            $env = $this->api('kickit.kick_player', [
                'check' => $badCheck,
                'sid'   => 1,
                'num'   => 0,
                'type'  => 0,
            ]);
            $this->assertTrue(
                $env['ok'],
                sprintf('expected ok envelope for check=%s, got: %s', var_export($badCheck, true), json_encode($env)),
            );
            $this->assertSame(
                'not_found',
                $env['data']['status'],
                sprintf('expected status=not_found for check=%s', var_export($badCheck, true)),
            );
        }
    }

    /**
     * #1423 follow-up #4 — paired with the SteamID guard, the IP
     * branch of `api_kickit_kick_player` (`type === 1`) needs the
     * same defense: a garbage `check` value reaches the iframe loop
     * with a `no_connect` envelope and an opaque iframe failure.
     * The fix uses `filter_var(FILTER_VALIDATE_IP)` so the structured
     * `not_found` envelope surfaces.
     */
    public function testKickPlayerReturnsNotFoundForMalformedIp(): void
    {
        $this->loginAsAdmin();
        foreach (['garbage', 'not.an.ip', "192.168.0.1\n", ''] as $badCheck) {
            $env = $this->api('kickit.kick_player', [
                'check' => $badCheck,
                'sid'   => 1,
                'num'   => 0,
                'type'  => 1,
            ]);
            $this->assertTrue($env['ok']);
            $this->assertSame(
                'not_found',
                $env['data']['status'],
                sprintf('expected status=not_found for IP check=%s', var_export($badCheck, true)),
            );
        }
    }
}
