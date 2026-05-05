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

SourceComms 0.9.266
Copyright (C) 2013-2014 Alexandr Duplishchev
Licensed under GNU GPL version 3, or later.
Page: <https://forums.alliedmods.net/showthread.php?p=1883705> - <https://github.com/d-ai/SourceComms>
*************************************************************************/

global $userbank, $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

new AdminTabs([
    ['name' => 'Add a block', 'permission' => ADMIN_OWNER|ADMIN_ADD_BAN]
], $userbank, $theme);

if (isset($_GET['mode']) && $_GET['mode'] == "delete") {
    // Inlined sourcebans.js helper (#1123 D1 prep): ShowBox is removed at D1; sb.message is in sb.js.
    echo "<script>sb.message.show('Ban Deleted', 'The ban has been deleted from SourceBans', 'green', '', true);</script>";
} elseif (isset($_GET['mode']) && $_GET['mode']=="unban") {
    // Inlined sourcebans.js helper (#1123 D1 prep): ShowBox is removed at D1; sb.message is in sb.js.
    echo "<script>sb.message.show('Player Unbanned', 'The Player has been unbanned from SourceBans', 'green', '', true);</script>";
}

if (isset($GLOBALS['IN_ADMIN'])) {
    define('CUR_AID', $userbank->GetAid());
}


// Inlined sourcebans.js helpers (#1123 D1 prep): LoadPrepareReblock / LoadPrepareBlockFromBan /
// LoadPasteBlock / ShowBox / applyBlockFields disappear at D1; rebuild on top of sb.api.call +
// a small DOM-prefill helper (window.__sbppApplyBlockFields) defined in this file's tail script.
if (isset($_GET["rebanid"])) {
    echo '<script type="text/javascript">sb.ready(function(){sb.api.call(Actions.CommsPrepareReblock,{bid:' . (int) $_GET["rebanid"] . '}).then(function(r){if(r&&r.ok&&r.data&&typeof window.__sbppApplyBlockFields==="function")window.__sbppApplyBlockFields(r.data);});});</script>';
} elseif (isset($_GET["blockfromban"])) {
    echo '<script type="text/javascript">sb.ready(function(){sb.api.call(Actions.CommsPrepareBlockFromBan,{bid:' . (int) $_GET["blockfromban"] . '}).then(function(r){if(r&&r.ok&&r.data&&typeof window.__sbppApplyBlockFields==="function")window.__sbppApplyBlockFields(r.data);});});</script>';
} elseif ((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
    $pNameJs = json_encode((string) $_GET['pName'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<script type=\"text/javascript\">sb.ready(function(){sb.message.show('Loading..','<b>Loading...</b><br><i>Please Wait!</i>','blue','',true);sb.hide('dialog-control');sb.api.call(Actions.CommsPaste,{sid:" . (int) $_GET['sid'] . ",name:" . $pNameJs . ",type:0}).then(function(r){if(r&&r.ok&&r.data){if(typeof window.__sbppApplyBlockFields==='function')window.__sbppApplyBlockFields(r.data);sb.show('dialog-control');sb.hide('dialog-placement');}else if(r&&r.ok===false&&r.error){sb.message.error('Error',r.error.message);sb.show('dialog-control');}});});</script>";
}

echo '<div id="admin-page-content">';
echo '<div class="tabcontent" id="Add a block">';
// SourceComms reuses the bans permission set: there is no
// ADMIN_ADD_COMM flag, so the gate uses ADMIN_OWNER|ADMIN_ADD_BAN.
// Splatting Perms::for(...) into the View pulls `can_add_ban` (and
// the owner-bypass) without re-deriving the bitmask here; the
// view-level property name stays `permission_addban` to match the
// legacy default-theme template's existing reference (#1123 A3 +
// SmartyTemplateRule's per-leg cross-check).
$perms = \Sbpp\View\Perms::for($userbank);
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminCommsAddView(
    permission_addban: $perms['can_add_ban'],
));
?>
</div>
<script type="text/javascript">
function changeReason(szListValue)
{
    $('dreason').style.display = (szListValue == "other" ? "block" : "none");
}
function ProcessBan()
{
    var reason = $('listReason')[$('listReason').selectedIndex].value;

    if (reason == "other") {
        reason = $('txtReason').value;
    }
    sb.api.call(Actions.CommsAdd, {
        nickname: $('nickname').value,
        type:     Number($('type').value),
        steam:    $('steam').value,
        length:   Number($('banlength').value),
        reason:   reason,
    }).then(function (r) {
        // Inlined sourcebans.js helpers (#1123 D1 prep): ShowBlockBox / TabToReload /
        // applyApiResponse are deleted at D1; rebuild on top of sb.message (sb.js, survives D1).
        // The iframe is load-bearing — pages/admin.blockit.php loops the enabled servers and
        // fires `sc_fw_block` via rcon for each one. Without it the DB row exists but no live
        // server learns about the gag/mute, matching the bans/kickit shape one branch above.
        if (r && r.ok && r.data && r.data.block) {
            var b = r.data.block;
            sb.message.show(
                'Block Added',
                'The block has been successfully added<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.blockit.php?check='
                    + encodeURIComponent(b.steam) + '&type=' + encodeURIComponent(b.type) + '&length=' + encodeURIComponent(b.length) + '"></iframe>',
                'green',
                'index.php?p=admin&c=comms',
                true
            );
            if (r.data.reload) setTimeout(function () { window.location.href = window.location.href.replace(/#\^.*$/, ''); }, 2000);
            return;
        }
        if (!r) return;
        if (r.redirect) return;
        if (r.ok === false) {
            if (r.error) sb.message.error('Error', r.error.message || 'Unknown error');
            return;
        }
        var data = r.data || {};
        if (data.message) {
            sb.message.show(data.message.title, data.message.body, data.message.kind, data.message.redir, data.message.noclose);
        }
        if (data.reload) {
            setTimeout(function () { window.location.href = window.location.href.replace(/#\^.*$/, ''); }, 2000);
        }
    });
}

// Inlined sourcebans.js helper (#1123 D1 prep): applyBlockFields disappears at D1; rebuild on top
// of sb.js primitives so reblock / blockfromban / pasteBlock all keep prefilling the form.
window.__sbppApplyBlockFields = function (d) {
    var byId = function (id) { return document.getElementById(id); };
    if (byId('nickname'))   byId('nickname').value   = d.nickname || '';
    if (byId('fromsub'))    byId('fromsub').value    = d.subid    || '';
    if (byId('steam'))      byId('steam').value      = d.steam    || '';
    if (byId('txtReason'))  byId('txtReason').value  = '';
    if (typeof window.selectLengthTypeReason === 'function') {
        window.selectLengthTypeReason(d.length || 0, d.type || 0, d.reason || '');
    }
    if (typeof window.swapTab === 'function') window.swapTab(0);
};
</script>
</div>
