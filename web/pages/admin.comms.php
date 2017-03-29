<?php
/*************************************************************************
This file is part of SourceBans++

Copyright � 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

SourceBans++ is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright � 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC BY-NC-SA 3.0
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
if (isset($GLOBALS['IN_ADMIN'])) {
    define('CUR_AID', $userbank->GetAid());
}


if (isset($_GET["rebanid"])) {
    echo '<script type="text/javascript">xajax_PrepareReblock("' . $_GET["rebanid"] . '");</script>';
} elseif (isset($_GET["blockfromban"])) {
    echo '<script type="text/javascript">xajax_PrepareBlockFromBan("' . $_GET["blockfromban"] . '");</script>';
} elseif ((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
    echo "<script type=\"text/javascript\">ShowBox('Loading..','<b>Loading...</b><br><i>Please Wait!</i>', 'blue', '', true);document.getElementById('dialog-control').setStyle('display', 'none');xajax_PasteBlock('" . (int) $_GET['sid'] . "', '" . addslashes($_GET['pName']) . "');</script>";
}

echo '<div id="admin-page-content">';
// Add Block
echo '<div id="0" style="display:none;">';
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
    var err = 0;
    var reason = $('listReason')[$('listReason').selectedIndex].value;

    if (reason == "other") {
        reason = $('txtReason').value;
    }
    if(!$('nickname').value)
    {
        $('nick.msg').setHTML('You must enter the nickname of the person you are banning');
        $('nick.msg').setStyle('display', 'block');
        err++;
    }else
    {
        $('nick.msg').setHTML('');
        $('nick.msg').setStyle('display', 'none');
    }

    if($('steam').value.length < 10)
    {
        $('steam.msg').setHTML('You must enter a valid STEAM ID or Community ID');
        $('steam.msg').setStyle('display', 'block');
        err++;
    }else
    {
        $('steam.msg').setHTML('');
        $('steam.msg').setStyle('display', 'none');
    }

    if(!reason)
    {
        $('reason.msg').setHTML('You must select or enter a reason for this block.');
        $('reason.msg').setStyle('display', 'block');
        err++;
    }else
    {
        $('reason.msg').setHTML('');
        $('reason.msg').setStyle('display', 'none');
    }

    if(err)
        return 0;

    xajax_AddBlock($('nickname').value,
        $('type').value,
        $('steam').value,
        $('banlength').value,
        reason
    );
}
</script>
</div>
