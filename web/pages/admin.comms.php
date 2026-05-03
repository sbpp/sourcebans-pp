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
    echo "<script>ShowBox('Ban Deleted', 'The ban has been deleted from SourceBans', 'green', '', true);</script>";
} elseif (isset($_GET['mode']) && $_GET['mode']=="unban") {
    echo "<script>ShowBox('Player Unbanned', 'The Player has been unbanned from SourceBans', 'green', '', true);</script>";
}

if (isset($GLOBALS['IN_ADMIN'])) {
    define('CUR_AID', $userbank->GetAid());
}


if (isset($_GET["rebanid"])) {
    echo '<script type="text/javascript">LoadPrepareReblock("' . (int) $_GET["rebanid"] . '");</script>';
} elseif (isset($_GET["blockfromban"])) {
    echo '<script type="text/javascript">LoadPrepareBlockFromBan("' . (int) $_GET["blockfromban"] . '");</script>';
} elseif ((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
    $pNameJs = json_encode((string) $_GET['pName'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<script type=\"text/javascript\">ShowBox('Loading..','<b>Loading...</b><br><i>Please Wait!</i>', 'blue', '', true);sb.hide('dialog-control');LoadPasteBlock(" . (int) $_GET['sid'] . ", " . $pNameJs . ");</script>";
}

echo '<div id="admin-page-content">';
echo '<div class="tabcontent" id="Add a block">';
$theme->assign('permission_addban', $userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN));
$theme->display('page_admin_comms_add.tpl');
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
    sb.api.call('comms.add', {
        nickname: $('nickname').value,
        type:     Number($('type').value),
        steam:    $('steam').value,
        length:   Number($('banlength').value),
        reason:   reason,
    }).then(function (r) {
        if (r && r.ok && r.data && r.data.block) {
            ShowBlockBox(r.data.block.steam, r.data.block.type, r.data.block.length);
            if (r.data.reload) TabToReload();
            return;
        }
        applyApiResponse(r);
    });
}
</script>
</div>
