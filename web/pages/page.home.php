<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

global $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
define('IN_HOME', true);

/**
 * Inlined sourcebans.js helpers (#1123 D1 prep): see page.servers.php for the canonical
 * definition. Duplicated under function_exists() because page.home.php builds its
 * LoadServerHostProperty()-equivalent calls before it requires page.servers.php, and we
 * need the helper definitions to land at the head of $server_qry.
 */
if (!function_exists('SbppServerQryHelpers')) {
    function SbppServerQryHelpers(): string
    {
        return <<<'JS'
if(typeof window.__sbppLoadServerHost!=="function"){window.__sbppLoadServerHost=function(sid){sb.api.call(Actions.ServersHostPlayers,{sid:sid,trunchostname:70}).then(function(r){if(!r||!r.ok||!r.data)return;var d=r.data,hostEl=sb.$id("host_"+sid),playersEl=sb.$id("players_"+sid),osEl=sb.$id("os_"+sid),vacEl=sb.$id("vac_"+sid),mapEl=sb.$id("map_"+sid);if(d.error==="connect"){var ipPort=(d.ip||"")+":"+(d.port||"");if(hostEl)hostEl.innerHTML="<b>Error connecting</b> (<i>"+ipPort+"</i>)";if(!d.is_owner){if(playersEl)playersEl.textContent="N/A";if(osEl)osEl.textContent="N/A";if(vacEl)vacEl.textContent="N/A";if(mapEl)mapEl.textContent="N/A";}return;}if(hostEl)hostEl.innerHTML=d.hostname;if(playersEl)playersEl.textContent=(d.players||0)+"/"+(d.maxplayers||0);if(osEl)osEl.innerHTML="<i class='"+(d.os_class||"")+" fa-2x'></i>";if(vacEl&&d.secure)vacEl.innerHTML="<i class='fas fa-shield-alt fa-2x'></i>";if(mapEl)mapEl.textContent=d.map||"";});};}
if(typeof window.__sbppLoadServerHostProperty!=="function"){window.__sbppLoadServerHostProperty=function(sid,obId,obProp){sb.api.call(Actions.ServersHostProperty,{sid:sid,trunchostname:100}).then(function(r){if(!r||!r.ok||!r.data)return;var text=r.data.error==="connect"?("Error connecting ("+(r.data.ip||"")+":"+(r.data.port||"")+")"):r.data.hostname;var el=sb.$id(obId);if(!el)return;if(obProp==="innerHTML")el.innerHTML=text;else el.setAttribute(obProp,text);});};}
JS;
    }
}

$totalstopped = (int) $GLOBALS['PDO']->query("SELECT count(name) AS cnt FROM `:prefix_banlog`")->single()['cnt'];

$rows = $GLOBALS['PDO']->query("SELECT bl.name, time, bl.sid, bl.bid, b.type, b.authid, b.ip,
                                CONCAT(se.ip,':',se.port) AS server_addr
								FROM `:prefix_banlog` AS bl
								LEFT JOIN `:prefix_bans` AS b ON b.bid = bl.bid
								LEFT JOIN `:prefix_servers` AS se ON se.sid = bl.sid
								ORDER BY time DESC LIMIT 10")->resultset();

$GLOBALS['server_qry'] = SbppServerQryHelpers();
$stopped               = [];
$blcount               = 0;
foreach ($rows as $row) {
    $info               = [];
    $info['date']       = Config::time($row['time']);
    $raw_name           = htmlspecialchars(stripslashes((string) $row['name']), ENT_NOQUOTES, 'UTF-8');
    $cleaned_name       = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);
    $info['name']       = htmlspecialchars(addslashes($cleaned_name), ENT_QUOTES, 'UTF-8');
    $info['short_name'] = trunc($cleaned_name, 40);
    $info['auth']       = $row['authid'];
    $info['ip']         = $row['ip'];
    $info['server']     = "block_" . $row['sid'] . "_$blcount";

    if ($row['type'] == 1) {
        if ($userbank->is_admin())
            $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['ip']) . '&advType=ip&Submit';
        else
            $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['name']) . '&advType=name';
    } else {
        $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['auth']) . '&advType=steamid&Submit';
    }
    $info['link_url'] = "window.location = '" . $info['search_link'] . "';";

    // To print a name in the popup instead an empty string
    if (empty($cleaned_name)) {
        $cleaned_name = "<i>No nickname present</i>";
    }
    $info['popup']    = "ShowBox('Blocked player: " . $cleaned_name . "', '" . $cleaned_name . " tried to enter<br />' + document.getElementById('" . $info['server'] . "').title + '<br />at " . $info['date'] . "<br /><div align=middle><a href=" . $info['search_link'] . ">Click here for ban details.</a></div>', 'red', '', true);";

    $GLOBALS['server_qry'] .= "__sbppLoadServerHostProperty(" . (int) $row['sid'] . ", 'block_" . (int) $row['sid'] . "_$blcount', 'title');";

    // sbpp2026 fields. Stored alongside the legacy keys above so the
    // new dashboard template reads `$player.bid` / `$player.sname`
    // without breaking the legacy template (which simply ignores extra
    // keys). Both themes share this view during the v2.0.0 rollout.
    $info['bid']           = (int) ($row['bid'] ?? 0);
    $info['sname']         = (string) ($row['server_addr'] ?? '');
    $info['blocked_human'] = $info['date'];

    $stopped []= $info;
    ++$blcount;
}

$BanCount = (int) $GLOBALS['PDO']->query("SELECT count(bid) AS cnt FROM `:prefix_bans`")->single()['cnt'];

$ActiveBanCount = (int) $GLOBALS['PDO']
    ->query("SELECT count(bid) AS cnt FROM `:prefix_bans`
             WHERE RemoveType IS NULL
               AND (length = 0 OR ends > UNIX_TIMESTAMP())")
    ->single()['cnt'];

$rows = $GLOBALS['PDO']->query("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid AS ba_sid, ad.user, CONCAT(se.ip,':',se.port) AS server_addr, se.sid AS se_sid, mo.icon, ba.RemoveType, ba.type
			    				FROM `:prefix_bans` AS ba
			    				LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
			    				LEFT JOIN `:prefix_servers` AS se ON se.sid = ba.sid
			    				LEFT JOIN `:prefix_mods` AS mo ON mo.mid = se.modid
			    				ORDER BY created DESC LIMIT 10")->resultset();
$bans = [];
foreach ($rows as $row) {
    $info = [];
    $info['temp']     = false;
    $info['perm']     = false;
    $info['unbanned'] = false;
    if ($row['length'] == 0) {
        $info['perm']     = true;
        $info['unbanned'] = false;
    } else {
        $info['temp']     = true;
        $info['unbanned'] = false;
    }
    $raw_name         = stripslashes($row['name']);
    $cleaned_name     = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);
    $info['name']    = htmlspecialchars(addslashes($cleaned_name), ENT_QUOTES, 'UTF-8');
    $info['created'] = Config::time($row['created']);
    $ltemp           = explode(",", $row['length'] == 0 ? 'Permanent' : SecondsToString(intval($row['length'])));
    $info['length']  = $ltemp[0];
    $info['icon']    = empty($row['icon']) ? 'web.png' : $row['icon'];
    $info['authid']  = $row['authid'];
    $info['ip']      = $row['ip'];
    if ($row['type'] == 1) {
        if ($userbank->is_admin())
            $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['ip']) . '&advType=ip&Submit';
        else
            $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['name']) . '&advType=name';
    } else {
        $info['search_link'] = 'index.php?p=banlist&advSearch=' . urlencode($info['authid']) . '&advType=steamid&Submit';
    }
    $info['link_url']   = "window.location = '" . $info['search_link'] . "';";
    $info['short_name'] = trunc($cleaned_name, 40);

    if ($row['RemoveType'] == 'D' || $row['RemoveType'] == 'U' || $row['RemoveType'] == 'E' || ($row['length'] && $row['ends'] < time())) {
        $info['unbanned']  = true;
        $info['ub_reason'] = match (true) {
            $row['RemoveType'] === 'D' => 'D',
            $row['RemoveType'] === 'U' => 'U',
            default                    => 'E',
        };
    } else {
        $info['unbanned'] = false;
    }

    // sbpp2026 fields, derived from the same row so the new template
    // reads handoff-style keys without re-querying. The legacy template
    // ignores extras. Stored raw — Smarty's global auto-escape
    // (init.php: $theme->setEscapeHtml(true)) handles HTML-escaping at
    // emit time; pre-escaping here would double-encode `&`/`<`/`>` in
    // ban reasons (AGENTS.md: "Store raw, escape on display").
    $info['bid']          = (int) $row['bid'];
    $info['reason']       = (string) ($row['reason'] ?? '');
    $info['sname']        = (string) ($row['server_addr'] ?? '');
    $info['length_human'] = $info['length'];
    $info['banned_human'] = $info['created'];
    // 'expired' (natural end) vs 'unbanned' (explicit D/U removal) so
    // .pill / .ban-row state classes can render them differently.
    if ($info['unbanned']) {
        $info['state'] = $info['ub_reason'] === 'E' ? 'expired' : 'unbanned';
    } elseif ($info['perm']) {
        $info['state'] = 'permanent';
    } else {
        $info['state'] = 'active';
    }

    array_push($bans, $info);
}

$CommCount = (int) $GLOBALS['PDO']->query("SELECT count(bid) AS cnt FROM `:prefix_comms`")->single()['cnt'];

$rows = $GLOBALS['PDO']->query("SELECT bid, ba.authid, ba.type, ba.name, created, ends, length, reason, ba.aid, ba.sid AS ba_sid, ad.user, CONCAT(se.ip,':',se.port) AS server_addr, se.sid AS se_sid, mo.icon, ba.RemoveType
				    				FROM `:prefix_comms` AS ba
				    				LEFT JOIN `:prefix_admins` AS ad ON ba.aid = ad.aid
				    				LEFT JOIN `:prefix_servers` AS se ON se.sid = ba.sid
				    				LEFT JOIN `:prefix_mods` AS mo ON mo.mid = se.modid
				    				ORDER BY created DESC LIMIT 10")->resultset();
$comms = [];
foreach ($rows as $row) {
    $info = [];
    $info['temp']     = false;
    $info['perm']     = false;
    $info['unbanned'] = false;

    if ($row['length'] == 0) {
        $info['perm']     = true;
        $info['unbanned'] = false;
    } else {
        $info['temp']     = true;
        $info['unbanned'] = false;
    }
    $raw_name             = stripslashes($row['name']);
    $cleaned_name         = mb_convert_encoding($raw_name, 'UTF-8', 'UTF-8');
    $unwanted_sequences   = ["\xF3\xA0\x80\xA1"];
    foreach ($unwanted_sequences as $sequence) {
        $cleaned_name = str_replace($sequence, '', $cleaned_name);
    }
    $cleaned_name = trim($cleaned_name);
    $info['name']        = htmlspecialchars(addslashes($cleaned_name), ENT_QUOTES, 'UTF-8');
    $info['created']     = Config::time($row['created']);
    $ltemp               = explode(",", $row['length'] == 0 ? 'Permanent' : SecondsToString(intval($row['length'])));
    $info['length']      = $ltemp[0];
    $info['icon']        = empty($row['icon']) ? 'web.png' : $row['icon'];
    $info['authid']      = $row['authid'];
    $info['search_link'] = 'index.php?p=commslist&advSearch=' . urlencode($info['authid']) . '&advType=steamid&Submit';
    $info['link_url']    = "window.location = '" . $info['search_link'] . "';";
    $info['short_name']  = trunc($cleaned_name, 40);
    $info['type']        = $row['type'] == 2 ? "fas fa-comment-slash fa-lg" : "fas fa-microphone-slash fa-lg";

    if ($row['RemoveType'] == 'D' || $row['RemoveType'] == 'U' || $row['RemoveType'] == 'E' || ($row['length'] && $row['ends'] < time())) {
        $info['unbanned']  = true;
        $info['ub_reason'] = match (true) {
            $row['RemoveType'] === 'D' => 'D',
            $row['RemoveType'] === 'U' => 'U',
            default                    => 'E',
        };
    } else {
        $info['unbanned'] = false;
    }

    // sbpp2026 fields, mirroring the bans loop above (raw — Smarty
    // escapes on display).
    $info['bid']          = (int) $row['bid'];
    $info['reason']       = (string) ($row['reason'] ?? '');
    $info['sname']        = (string) ($row['server_addr'] ?? '');
    $info['length_human'] = $info['length'];
    $info['banned_human'] = $info['created'];
    // Lucide icon name. ba.type=2 is text-chat block, otherwise voice.
    $info['lucide_icon']  = $row['type'] == 2 ? 'message-square-off' : 'mic-off';
    if ($info['unbanned']) {
        $info['state'] = $info['ub_reason'] === 'E' ? 'expired' : 'unbanned';
    } elseif ($info['perm']) {
        $info['state'] = 'permanent';
    } else {
        $info['state'] = 'active';
    }

    array_push($comms, $info);
}


require(TEMPLATES_PATH . "/page.servers.php"); //populates $serversView
/** @var \Sbpp\View\ServersView $serversView */

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\HomeDashboardView(
    dashboard_title: (string) (Config::get('dash.intro.title') ?? ''),
    dashboard_text: \Sbpp\Markup\IntroRenderer::renderIntroText(
        (string) (Config::get('dash.intro.text') ?? '')
    ),
    dashboard_lognopopup: Config::getBool('dash.lognopopup'),
    players_blocked: $stopped,
    total_blocked: $totalstopped,
    players_banned: $bans,
    total_bans: $BanCount,
    active_bans: $ActiveBanCount,
    players_commed: $comms,
    total_comms: $CommCount,
    access_bans: $serversView->access_bans,
    server_list: $serversView->server_list,
    total_servers: count($serversView->server_list),
    IN_SERVERS_PAGE: $serversView->IN_SERVERS_PAGE,
    opened_server: $serversView->opened_server,
));
