<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Endpoints used by the admin.kickit.php iframe — the page-by-page
"is the player still on this server, then kick them" loop.
*************************************************************************/

use SteamID\SteamID;

/**
 * Lists every enabled server with its rcon status. Replaces the legacy
 * LoadServers() that emitted addScript() calls per row.
 *
 * @return array{servers: array<int, array{num:int, sid:int, has_rcon:bool}>}
 */
function api_kickit_load_servers(array $params): array
{
    $servers = $GLOBALS['PDO']
        ->query("SELECT sid, rcon FROM `:prefix_servers` WHERE enabled = 1 ORDER BY modid, sid")
        ->resultset();

    $out = [];
    $id  = 0;
    foreach ($servers as $s) {
        $out[] = [
            'num'      => $id,
            'sid'      => (int)$s['sid'],
            'has_rcon' => !empty($s['rcon']),
        ];
        $id++;
    }
    return ['servers' => $out];
}

/**
 * Try to kick a single player on a single server. Used per-row by the
 * iframe's per-server JS loop.
 *
 * The iframe is invoked from two different surfaces with subtly
 * different semantics (#1439):
 *
 *  - **`mode === 'ban'`** (default, backward-compat) — the iframe is
 *    embedded inside the "Ban Added" success dialog on
 *    `admin.bans.php`. A ban row was JUST inserted; the per-server
 *    loop's job is to (a) record which server actually executed the
 *    kick on the just-created ban row (`UPDATE :prefix_bans SET sid`),
 *    and (b) tell the player they've been banned via the rcon kick
 *    message so they know to visit the panel for appeals.
 *  - **`mode === 'kick'`** — the iframe loaded standalone from the
 *    right-click context menu on the public servers page (`?p=servers`
 *    via `web/scripts/server-context-menu.js`). There is NO ban row;
 *    the operator just wants to kick the player without banning. We
 *    MUST skip the `:prefix_bans` UPDATE here — without `mode`, the
 *    UPDATE would silently re-attribute ANY of the player's existing
 *    active bans (`RemovedBy IS NULL`) to whatever server happened to
 *    be the kick target, corrupting the audit trail (#1439 secondary
 *    impact). The rcon kick message is also wrong for this flow —
 *    the player isn't banned and CAN rejoin, so "You have been
 *    banned…" lies to them (#1439 user-reported symptom).
 *
 * Unrecognised `mode` values are coerced to `'ban'` (backward-compat
 * — old callers that don't supply the param keep working).
 *
 * @return array{
 *   status: 'kicked'|'not_found'|'no_connect',
 *   hostname: string,
 *   ip?: string,
 *   port?: string,
 *   sid: int,
 *   num: int,
 * }
 */
function api_kickit_kick_player(array $params): array
{
    $check = (string)($params['check'] ?? '');
    $sid   = (int)($params['sid']   ?? 0);
    $num   = (int)($params['num']   ?? 0);
    $type  = (int)($params['type']  ?? 0);
    $mode  = (string)($params['mode'] ?? 'ban');
    if ($mode !== 'kick') {
        // Strict allowlist: only 'kick' enters the no-UPDATE / kicked-message branch.
        // Everything else (including unsupplied + hostile + typo) falls through to
        // 'ban' — the historical default before #1439, so old iframe embeds keep
        // working without a paired ban-side flip.
        $mode = 'ban';
    }

    // #1423 follow-up #4 — gate the `check` shape BEFORE we reach the
    // `SteamID::compare()` call below. For `type === 0` (Steam-ID
    // kick), `compare()` routes through `toSteam64()` → the library
    // throws `Exception('Invalid SteamID input!')` on any input that
    // fails the strict shape gate. The exception escapes the handler
    // via `Api::handle`'s `Throwable` fallback as a generic 500
    // envelope; the iframe loop then can't tell "no match" apart from
    // "your input was garbage", and a hostile caller posting
    // `?check=garbage&type=0` reliably 500s the panel. For
    // `type === 1` (IP-address kick) we run `filter_var` instead so a
    // malformed IP returns the standard `not_found` envelope rather
    // than triggering the SteamID compare on whatever the operator
    // typed in the wrong box.
    if ($type === 0 && !SteamID::isValidID($check)) {
        return [
            'status' => 'not_found',
            'sid'    => $sid,
            'num'    => $num,
            'hostname' => '',
            'ip'     => '',
            'port'   => '',
        ];
    }
    if ($type === 1 && !filter_var($check, FILTER_VALIDATE_IP)) {
        return [
            'status' => 'not_found',
            'sid'    => $sid,
            'num'    => $num,
            'hostname' => '',
            'ip'     => '',
            'port'   => '',
        ];
    }

    $serverInfo = $GLOBALS['PDO']->query("SELECT ip, port FROM `:prefix_servers` WHERE sid = :sid");
    $GLOBALS['PDO']->bind(':sid', $sid);
    $sdata = $GLOBALS['PDO']->single() ?: ['ip' => '', 'port' => ''];

    $ret = rcon('status', $sid);
    if (!$ret) {
        return [
            'status' => 'no_connect',
            'sid'    => $sid,
            'num'    => $num,
            'ip'     => $sdata['ip'],
            'port'   => $sdata['port'],
            'hostname' => '',
        ];
    }

    if (preg_match('/hostname:[ ]*(.+)/', $ret, $hostname)) {
        $hostname = trunc(htmlspecialchars($hostname[1]), 25);
    } else {
        $hostname = '';
    }

    $kickMessage    = _api_kickit_build_kick_message($mode, Host::complete());
    $shouldUpdateBan = _api_kickit_should_update_ban_sid($mode);

    foreach (parseRconStatus($ret) as $player) {
        if ($type === 0) {
            if (SteamID::compare($player['steamid'], $check)) {
                if ($shouldUpdateBan) {
                    // Track which server executed the kick on the just-created
                    // ban row. Only meaningful when a ban exists — see the
                    // docblock and #1439 for the kick-only data-integrity
                    // concern that motivated gating this UPDATE on `mode`.
                    $GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET sid = :sid WHERE authid = :authid AND RemovedBy IS NULL");
                    $GLOBALS['PDO']->bind(':sid', $sid);
                    $GLOBALS['PDO']->bind(':authid', $check);
                    $GLOBALS['PDO']->execute();
                }

                rcon("kickid {$player['id']} \"$kickMessage\"", $sid);

                return ['status' => 'kicked', 'sid' => $sid, 'num' => $num, 'hostname' => $hostname, 'ip' => $sdata['ip'], 'port' => $sdata['port']];
            }
        } elseif ($type === 1) {
            if (($player['ip'] ?? null) === $check) {
                if ($shouldUpdateBan) {
                    $GLOBALS['PDO']->query("UPDATE `:prefix_bans` SET sid = :sid WHERE ip = :ip AND RemovedBy IS NULL");
                    $GLOBALS['PDO']->bind(':sid', $sid);
                    $GLOBALS['PDO']->bind(':ip', $check);
                    $GLOBALS['PDO']->execute();
                }

                rcon("kickid {$player['id']} \"$kickMessage\"", $sid);

                return ['status' => 'kicked', 'sid' => $sid, 'num' => $num, 'hostname' => $hostname, 'ip' => $sdata['ip'], 'port' => $sdata['port']];
            }
        }
    }

    return ['status' => 'not_found', 'sid' => $sid, 'num' => $num, 'hostname' => $hostname, 'ip' => $sdata['ip'], 'port' => $sdata['port']];
}

/**
 * Build the rcon `kickid` reason string for the kickit flow.
 *
 * Factored out of {@link api_kickit_kick_player()} so the
 * mode-branching contract is unit-testable without standing up a real
 * RCON probe + matching status response (the rcon `kickid` round-trip
 * sits behind a UDP socket that the integration test surface
 * deliberately doesn't mock — see `web/includes/system-functions.php`
 * `rcon()`).
 *
 * The kick-only branch's message is intentionally short: no domain
 * suffix, because a kicked-not-banned player has nothing to look up
 * on the panel side and the rcon `kickid` reason field is single-line
 * — long suffixes truncate awkwardly in the user's disconnect dialog.
 *
 * @param 'ban'|'kick' $mode  caller is responsible for the allowlist
 *                            (the handler coerces unrecognised values
 *                            to 'ban' before calling here)
 * @param string $domain      output of {@see Host::complete()} —
 *                            only consumed by the 'ban' branch; passed
 *                            unconditionally so the signature is
 *                            mode-agnostic and easier to unit-test
 */
function _api_kickit_build_kick_message(string $mode, string $domain): string
{
    return $mode === 'kick'
        ? 'You have been kicked from this server'
        : "You have been banned by this server, check $domain for more info";
}

/**
 * Decide whether to run the `UPDATE :prefix_bans SET sid = :sid …` write
 * that records which server executed the kick on the just-created ban row.
 *
 * Factored out of {@link api_kickit_kick_player()} for the same reason
 * {@link _api_kickit_build_kick_message()} was: it pins the
 * mode-branching contract in a unit-testable shape WITHOUT standing up
 * a real RCON probe + matching status response (the
 * `kicked`-branch UPDATE site sits BEHIND `rcon('status', $sid)`'s UDP
 * socket, which the integration test surface deliberately doesn't
 * mock — see `system-functions.php` `rcon()`). Without this extraction
 * the only way to verify "kick mode does NOT mutate ban rows" would be
 * to stub `rcon()` itself, which is a separate refactor. The grep-shaped
 * static regression guard
 * (`KickitTest::testHandlerInvokesShouldUpdateHelperBeforeUpdate`)
 * confirms the handler still calls this helper at the right place;
 * the unit test confirms the helper returns the right verdict per mode.
 *
 * @param 'ban'|'kick' $mode  caller is responsible for the allowlist
 *                            (the handler coerces unrecognised values
 *                            to 'ban' before calling here)
 * @return bool  `true` when the handler should run the UPDATE, `false`
 *               when it should skip it (kick-only flow has no ban row
 *               to attribute the kick to, and running the UPDATE would
 *               silently re-attribute ANY of the kicked player's
 *               existing active bans to the kick-target server)
 */
function _api_kickit_should_update_ban_sid(string $mode): bool
{
    return $mode === 'ban';
}
