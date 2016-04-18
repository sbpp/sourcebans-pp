<?php
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

global $userbank, $theme; if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}if(isset($GLOBALS['IN_ADMIN']))define('CUR_AID', $userbank->GetAid());


if(isset($_GET["rebanid"]))
{
	echo '<script type="text/javascript">xajax_PrepareReblock("'.$_GET["rebanid"].'");</script>';
}elseif(isset($_GET["blockfromban"]))
{
	echo '<script type="text/javascript">xajax_PrepareBlockFromBan("'.$_GET["blockfromban"].'");</script>';
}elseif((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
	echo "<script type=\"text/javascript\">ShowBox('Loading..','<b>Loading...</b><br><i>Please Wait!</i>', 'blue', '', true);document.getElementById('dialog-control').setStyle('display', 'none');xajax_PasteBlock('".(int)$_GET['sid']."', '".addslashes($_GET['pName'])."');</script>";
}

echo '<div id="admin-page-content">';
	// Add Block
	echo '<div id="0" style="display:none;">';
		$theme->assign('permission_addban', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN));
		$theme->display('page_admin_comms_add.tpl');
	echo '</div>';
?>

<script type="text/javascript">
function changeReason(szListValue)
{
	$('dreason').style.display = (szListValue == "other" ? "block" : "none");
}
function ProcessBan()
{
	var err = 0;
	var reason = $('listReason')[$('listReason').selectedIndex].value;

	if (reason == "other")
		reason = $('txtReason').value;

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
				 reason);
}
</script>
</div>