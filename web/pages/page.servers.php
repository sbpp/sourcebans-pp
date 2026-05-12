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

global $theme, $userbank;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

/**
 * Vanilla server-host populate helpers, emitted into
 * $GLOBALS['server_qry'] from page.{servers,home}.php and rendered into
 * any third-party theme that forked the pre-v2.0.0 default and still
 * emits `{$query nofilter}` from its `core/footer.tpl`. The shipped
 * v2.0.0 default theme's footer does not emit `{$query nofilter}`, so
 * neither the helper definition nor the calls run there.
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
    // The shipped v2.0.0 default theme's footer does not emit
    // `{$query nofilter}`, so this payload only runs under any
    // third-party theme that forked the pre-v2.0.0 default and
    // preserved the legacy footer pattern (which wraps the value in
    // `<script>...</script>`). Emit raw JS accordingly.
    $GLOBALS['server_qry'] = SbppServerQryHelpers();
    if (isset($_GET['s'])) {
        $number = (int) $_GET['s'];
    }
}

// `md.name` (mod display name) is rendered next to the mod icon as
// a short tag (e.g. "TF2") so the card is meaningful before the live
// UDP query lands.
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

// Pull per-flag perms by name rather than splatting `Perms::for(...)`
// whole — the helper returns every ADMIN_* flag, but PHP 8.1 throws
// "Unknown named parameter" on any key the View doesn't declare.
// Listing by hand also makes the View's permission surface
// self-documenting (matches the admin.settings.php / admin.admins.php
// pattern).
$serversPerms = \Sbpp\View\Perms::for($userbank);
$serversView = new \Sbpp\View\ServersView(
    server_list: $servers,
    opened_server: $number,
    can_add_server: $serversPerms['can_add_server'],
    // Right-click context-menu hint + JS include are gated on
    // ADMIN_OWNER | ADMIN_ADD_BAN (the `can_add_ban` slot in the
    // Perms::for() snapshot). The SteamID side-channel the menu
    // reads off `Actions.ServersHostPlayers` is independently
    // server-side gated on the same permission AND per-server RCON
    // access, so a partial-permission caller without the second
    // check still only sees rows that have no `steamid` field
    // (`renderPlayers` skips the menu wiring on those). Mirroring
    // the gate here keeps the chrome consistent with what the
    // backend actually surfaces.
    can_use_context_menu: $serversPerms['can_add_ban'],
);

if (!defined('IN_HOME')) {
    \Sbpp\View\Renderer::render($theme, $serversView);
} else {
    \Sbpp\View\Renderer::assign($theme, $serversView);
}
