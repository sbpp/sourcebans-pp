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

/**
 * Inlined sourcebans.js helpers (#1123 D1 prep): vanilla replacements for
 * LoadServerHost / LoadServerHostProperty. Both are emitted into
 * $GLOBALS['server_qry'] from page.{servers,home}.php and rendered through
 * legacy themes' core/footer.tpl. Post-D1 sourcebans.js is gone, so we
 * define the helper as window.__sbppLoadServerHost{,Property} and call those
 * instead. The new sbpp2026 theme's footer doesn't emit {$query nofilter} so
 * neither the helper definition nor the calls run there; legacy + third-party
 * themes that copy the legacy footer pattern still get the populate behavior.
 *
 * Wrapped in function_exists so the same helper definition can ship from
 * page.home.php too without redefining the function.
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

$number = -1;
if (!defined('IN_HOME')) {
    // The legacy default theme wraps {$query nofilter} in <script>...</script> via core/footer.tpl,
    // so we emit RAW JS here. The new sbpp2026 footer drops {$query nofilter} entirely, so this
    // payload only runs under themes that preserved the legacy footer pattern.
    $GLOBALS['server_qry'] = SbppServerQryHelpers();
    if (isset($_GET['s'])) {
        $number = (int) $_GET['s'];
    }
}

// `md.name` (mod display name) is added for the sbpp2026 card label
// (#1123 B5). The legacy default theme ignores extra row keys; the new
// theme renders it next to the mod icon as a short tag (e.g. "TF2") so
// the card is meaningful before the live UDP query lands.
$rows = $GLOBALS['PDO']->query("SELECT se.sid, se.ip, se.port, se.modid, se.rcon, md.icon, md.name AS mod_name FROM `:prefix_servers` se LEFT JOIN `:prefix_mods` md ON md.mid=se.modid WHERE se.sid > 0 AND se.enabled = 1 ORDER BY se.modid, se.sid")->resultset();
$servers = [];
$i       = 0;
foreach ($rows as $row) {
    $info          = [];
    $info['sid']   = $row['sid'];
    $info['ip']    = $row['ip'];
    $info['dns']   = gethostbyname($row['ip']);
    $info['port']  = $row['port'];
    $info['icon']  = $row['icon'];
    $info['mod']   = (string) ($row['mod_name'] ?? '');
    $info['index'] = $i;
    if (defined('IN_HOME')) {
        $info['evOnClick'] = "window.location = 'index.php?p=servers&s=" . $info['index'] . "';";
    }

    $GLOBALS['server_qry'] .= '__sbppLoadServerHost(' . (int) $info['sid'] . ');';
    array_push($servers, $info);
    $i++;
}

$serversView = new \Sbpp\View\ServersView(
    access_bans: $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN),
    server_list: $servers,
    IN_SERVERS_PAGE: !defined('IN_HOME'),
    opened_server: $number,
);

if (!defined('IN_HOME')) {
    \Sbpp\View\Renderer::render($theme, $serversView);
} else {
    \Sbpp\View\Renderer::assign($theme, $serversView);
}
