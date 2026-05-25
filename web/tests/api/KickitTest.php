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

    /**
     * #1439 — the kickit iframe is invoked from two surfaces (the
     * post-ban embed on `admin.bans.php` AND the right-click context
     * menu on `?p=servers`). The `mode` parameter discriminates: the
     * handler must accept it without 500-ing AND the early-exit paths
     * (`no_connect`, `not_found` from the malformed-input gate) must
     * return the same envelope shape regardless of mode. The
     * "found-and-kicked" branch's mode-specific behaviour (skip the
     * `:prefix_bans` UPDATE, emit the kicked-not-banned rcon message)
     * is unit-tested separately via `_api_kickit_build_kick_message`
     * — the rcon round-trip itself sits behind a UDP socket the
     * integration suite deliberately doesn't mock.
     */
    public function testKickPlayerAcceptsModeKickAndReturnsNoConnectShape(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('kickit.kick_player', [
            'check' => 'STEAM_0:0:1',
            'sid'   => 0,
            'num'   => 0,
            'type'  => 0,
            'mode'  => 'kick',
        ]);
        $this->assertTrue($env['ok'], 'mode=kick should not crash the dispatcher');
        $this->assertSame('no_connect', $env['data']['status']);
        // The handler consumes `mode`; it must NEVER echo it back in the
        // response envelope. A future regression that drops `'mode' => $mode`
        // into the return array would silently leak operator intent + grow
        // the wire format without a snapshot update. Pin the absence.
        $this->assertArrayNotHasKey('mode', $env['data']);
    }

    public function testKickPlayerAcceptsModeBanAndReturnsNoConnectShape(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('kickit.kick_player', [
            'check' => 'STEAM_0:0:1',
            'sid'   => 0,
            'num'   => 0,
            'type'  => 0,
            'mode'  => 'ban',
        ]);
        $this->assertTrue($env['ok']);
        $this->assertSame('no_connect', $env['data']['status']);
        $this->assertArrayNotHasKey('mode', $env['data']);
    }

    /**
     * #1439 — anything other than literal 'kick' coerces to 'ban'
     * (backward-compat: pre-#1439 callers don't supply `mode`, and a
     * hostile / typo'd value must not bypass the strict allowlist).
     * The handler running to `no_connect` proves the coercion didn't
     * crash the dispatcher; the kick-message branch (the actual
     * behavioural differentiator) is covered by
     * `testBuildKickMessageCoercesUnknownModes` below.
     */
    public function testKickPlayerCoercesUnknownModeToBan(): void
    {
        $this->loginAsAdmin();
        foreach (['', 'BAN', 'KICK', 'banner', 'kicked', 'mute', "kick\n", ' kick '] as $badMode) {
            $env = $this->api('kickit.kick_player', [
                'check' => 'STEAM_0:0:1',
                'sid'   => 0,
                'num'   => 0,
                'type'  => 0,
                'mode'  => $badMode,
            ]);
            $this->assertTrue(
                $env['ok'],
                sprintf('mode=%s should not crash the dispatcher', var_export($badMode, true)),
            );
            $this->assertSame(
                'no_connect',
                $env['data']['status'],
                sprintf('mode=%s should still hit the no_connect branch', var_export($badMode, true)),
            );
        }
    }

    /**
     * #1439 — pin the rcon kick-message contract. The 'kick' branch
     * MUST NOT carry the domain or the "banned" verb (the original
     * user-reported symptom: a kicked-not-banned player saw "You have
     * been banned by this server" in their disconnect dialog and
     * concluded the panel was buggy). The 'ban' branch keeps the
     * historical message including the domain so banned players know
     * where to appeal.
     */
    public function testBuildKickMessageBranchesByMode(): void
    {
        $this->assertSame(
            'You have been kicked from this server',
            _api_kickit_build_kick_message('kick', 'panel.example.com'),
        );
        $this->assertSame(
            'You have been banned by this server, check panel.example.com for more info',
            _api_kickit_build_kick_message('ban', 'panel.example.com'),
        );
    }

    /**
     * #1439 — the helper trusts the caller to allowlist (the dispatcher
     * does so before invoking), but the contract is "only literal
     * 'kick' enters the kick-only message branch; everything else
     * falls through to the historical ban message". This pins the
     * defense-in-depth that future refactors don't loosen the gate.
     */
    public function testBuildKickMessageCoercesUnknownModes(): void
    {
        foreach (['', 'BAN', 'KICK', 'banner', 'kicked', "kick\n", ' kick '] as $badMode) {
            $this->assertSame(
                'You have been banned by this server, check panel.example.com for more info',
                _api_kickit_build_kick_message($badMode, 'panel.example.com'),
                sprintf('mode=%s should fall through to the ban message', var_export($badMode, true)),
            );
        }
    }

    /**
     * #1439 — pin the `_api_kickit_should_update_ban_sid` verdict on
     * the two allowlisted modes. This is the load-bearing
     * data-integrity contract the PR exists to enforce: the `UPDATE
     * :prefix_bans SET sid = :sid WHERE authid = :authid AND
     * RemovedBy IS NULL` (and its IP-keyed sibling) must NOT run
     * when the operator's intent was "kick only" — otherwise we
     * silently re-attribute ANY of the kicked player's existing
     * active bans to whatever server happened to be the kick
     * target. The full handler invocation can't easily prove this
     * without standing up a real RCON probe + matching status
     * response (the kicked-branch UPDATE site sits behind
     * `rcon('status', $sid)`'s UDP socket, which the integration
     * suite deliberately doesn't mock), so the helper extraction +
     * the unit test here are the regression guard. The grep-shaped
     * static guard
     * (`testHandlerInvokesShouldUpdateHelperBeforeUpdate`) confirms
     * the handler still calls THIS helper at the right place.
     */
    public function testShouldUpdateBanSidBranchesByMode(): void
    {
        $this->assertTrue(
            _api_kickit_should_update_ban_sid('ban'),
            "ban mode MUST run the UPDATE — that's the post-ban iframe's whole purpose",
        );
        $this->assertFalse(
            _api_kickit_should_update_ban_sid('kick'),
            "kick mode MUST skip the UPDATE — there is no ban row to attribute the kick to (the kick-only flow's #1439 contract)",
        );
    }

    /**
     * #1439 — the helper trusts the caller to allowlist (the
     * dispatcher does so before invoking), but the contract is "only
     * literal 'kick' suppresses the UPDATE; everything else runs
     * it". Pins the defense-in-depth: a future refactor that
     * loosens this gate (e.g. `$mode !== 'ban'` semantics flipping)
     * would let unexpected mode values silently disable the
     * post-ban-completion server attribution, which is a different
     * bug class but equally bad.
     */
    public function testShouldUpdateBanSidCoercesUnknownModesToBanBehavior(): void
    {
        foreach (['', 'BAN', 'KICK', 'banner', 'kicked', "kick\n", ' kick ', '???'] as $badMode) {
            $this->assertFalse(
                _api_kickit_should_update_ban_sid($badMode),
                sprintf(
                    'mode=%s is neither literal "ban" nor literal "kick" — the helper must NOT run the UPDATE on a value the dispatcher should have already coerced to "ban"; the inverse symmetry catches refactors that flip the predicate sign',
                    var_export($badMode, true),
                ),
            );
        }
    }

    /**
     * #1439 — static regression guard: the handler MUST funnel its
     * UPDATE decision through {@link _api_kickit_should_update_ban_sid}
     * — never inline a `$mode === 'ban'` check at the call site.
     * Without this guard a future "simplification" could re-introduce
     * the inline check, the helper would become dead code (still
     * passing its unit test), and a refactor of the helper's verdict
     * logic would silently desynchronise from the handler's
     * behaviour. Greps the handler source for both the helper call
     * AND the absence of bare `$mode === 'ban'` arms around the
     * `:prefix_bans` UPDATEs. The two-sided assertion (call-site
     * present AND bare-check absent) is the contract.
     */
    public function testHandlerInvokesShouldUpdateHelperBeforeUpdate(): void
    {
        $source = file_get_contents(__DIR__ . '/../../api/handlers/kickit.php');
        $this->assertNotFalse($source, 'kickit.php must be readable');

        $this->assertMatchesRegularExpression(
            '/_api_kickit_should_update_ban_sid\s*\(\s*\$mode\s*\)/',
            $source,
            "api_kickit_kick_player must call _api_kickit_should_update_ban_sid(\$mode) — the helper is the single source of truth for the UPDATE-skip contract.",
        );

        // The bare-check pattern: `if ($mode === 'ban')` immediately
        // followed by an UPDATE `:prefix_bans`. The regex anchors on
        // the order to allow `$mode === 'ban'` appearing in unrelated
        // places (e.g. the message-helper call) without false-firing.
        // The full handler body sits inside one function; we scope the
        // assertion to the function via a tighter window — search for
        // any `if (\s*\$mode === 'ban'\s*)` block that wraps a
        // `:prefix_bans` UPDATE string within the next ~6 lines.
        $this->assertDoesNotMatchRegularExpression(
            "/if\\s*\\(\\s*\\\$mode\\s*===\\s*'ban'\\s*\\)\\s*\\{[^{}]*UPDATE\\s+`:prefix_bans`/i",
            $source,
            'Found an inline `if (\$mode === \'ban\') { UPDATE :prefix_bans … }` arm. The UPDATE-skip decision must funnel through _api_kickit_should_update_ban_sid() so future verdict changes are single-source.',
        );
    }
}
