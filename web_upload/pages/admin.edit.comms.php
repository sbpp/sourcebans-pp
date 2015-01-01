<?php
/**
 * =============================================================================
 * Edit a ban
 *
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id: admin.edit.ban.php 258 2009-04-30 13:33:37Z tsunami $
 * =============================================================================
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}

global $theme;

if ($_GET['key'] != $_SESSION['banlist_postkey'])
{
	echo '<script>ShowBox("Error", "Possible hacking attempt (URL Key mismatch)!", "red", "index.php?p=admin&c=comms");</script>';
	PageDie();
}
if(!isset($_GET['id']) || !is_numeric($_GET['id']))
{
	echo '<script>ShowBox("Error", "No block id specified. Please only follow links!", "red", "index.php?p=admin&c=comms");</script>';
	PageDie();
}

$res = $GLOBALS['db']->GetRow("
    				SELECT bid, ba.type, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, ad.gid
    				FROM ".DB_PREFIX."_comms AS ba
    				LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
    				WHERE bid = {$_GET['id']}");

if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS)&&(!$userbank->HasAccess(ADMIN_EDIT_OWN_BANS) && $res[8]!=$userbank->GetAid())&&(!$userbank->HasAccess(ADMIN_EDIT_GROUP_BANS) && $res->fields['gid']!=$userbank->GetProperty('gid')))
{
	echo '<script>ShowBox("Error", "You don\'t have access to this!", "red", "index.php?p=admin&c=comms");</script>';
	PageDie();
}

isset($_GET["page"])?$pagelink = "&page=".$_GET["page"]:$pagelink = "";

$errorScript = "";

if(isset($_POST['name']))
{
	$_POST['steam'] = trim($_POST['steam']);
	$_POST['type'] = (int)$_POST['type'];
	
	// Form Validation
	$error = 0;
	// If they didn't type a steamid
	if(empty($_POST['steam']))
	{
		$error++;
		$errorScript .= "$('steam.msg').innerHTML = 'You must type a Steam ID or Community ID';";
		$errorScript .= "$('steam.msg').setStyle('display', 'block');";
	}
	else if((!is_numeric($_POST['steam']) 
	&& !validate_steam($_POST['steam']))
	|| (is_numeric($_POST['steam']) 
	&& (strlen($_POST['steam']) < 15
	|| !validate_steam($_POST['steam'] = FriendIDToSteamID($_POST['steam'])))))
	{
		$error++;
		$errorScript .= "$('steam.msg').innerHTML = 'Please enter a valid Steam ID or Community ID';";
		$errorScript .= "$('steam.msg').setStyle('display', 'block');";
	}
	
	// Didn't type a custom reason
	if($_POST['listReason'] == "other" && empty($_POST['txtReason']))
	{
		$error++;
		$errorScript .= "$('reason.msg').innerHTML = 'You must type a reason';";
		$errorScript .= "$('reason.msg').setStyle('display', 'block');";
	}
	
	// prune any old bans
	PruneComms();
	
	if($error == 0)
	{
		// Check if the new steamid is already banned
		$chk = $GLOBALS['db']->GetRow("SELECT count(bid) AS count FROM ".DB_PREFIX."_comms WHERE authid = ? AND RemovedBy IS NULL AND type = ? AND bid != ? AND (length = 0 OR ends > UNIX_TIMESTAMP())", array($_POST['steam'], (int)$_POST['type'], (int)$_GET['id']));
		if((int)$chk[0] > 0)
		{
			$error++;
			$errorScript .= "$('steam.msg').innerHTML = 'This SteamID is already blocked';";
			$errorScript .= "$('steam.msg').setStyle('display', 'block');";
		}
		else
		{
			// Check if player is immune
			$admchk = $userbank->GetAllAdmins();
			foreach($admchk as $admin)
			{
				if($admin['authid'] == $_POST['steam'] && $userbank->GetProperty('srv_immunity') < $admin['srv_immunity'])
				{
					$error++;
					$errorScript .= "$('steam.msg').innerHTML = 'Admin ".$admin['user']." is immune';";
					$errorScript .= "$('steam.msg').setStyle('display', 'block');";
					break;
				}
			}
		}
	}
	
	$_POST['name'] = RemoveCode($_POST['name']);
	$reason = RemoveCode(trim($_POST['listReason'] == "other"?$_POST['txtReason']:$_POST['listReason']));
	
	if(!$_POST['banlength'])
		$_POST['banlength'] = 0;
	else
		$_POST['banlength'] = (int)$_POST['banlength']*60;
	
	// Show the new values in the form
	$res['name'] = $_POST['name'];
	$res['authid'] = $_POST['steam'];
	
	$res['length'] = $_POST['banlength'];
	$res['type'] = $_POST['type'];
	$res['reason'] = $reason;
	
	// Only process if there are still no errors
	if($error == 0)
	{
		$lengthrev = $GLOBALS['db']->Execute("SELECT length, authid, type FROM ".DB_PREFIX."_comms WHERE bid = '".(int)$_GET['id']."'");
		
		
		$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_comms SET
										`name` = ?, `type` = ?, `reason` = ?, `authid` = ?,
										`length` = ?,
										`ends` 	 =  `created` + ?
										WHERE bid = ?", array($_POST['name'], $_POST['type'], $reason, $_POST['steam'], $_POST['banlength'], $_POST['banlength'], (int)$_GET['id']));
		
		
		if($_POST['banlength'] != $lengthrev->fields['length'])
			$log = new CSystemLog("m", "Block edited", "Block for (" . $lengthrev->fields['authid'] . ") has been updated, before: length ".$lengthrev->fields['length'].", type ".$lengthrev->fields['type']."; now: length ".$_POST['banlength']." type ".$_POST->fields['type']);
		echo '<script>ShowBox("Block updated", "The block has been updated successfully", "green", "index.php?p=commslist'.$pagelink.'");</script>';
	}
}

if(!$res)
{
	echo '<script>ShowBox("Error", "There was an error getting details. Maybe the block has been deleted?", "red", "index.php?p=commslist'.$pagelink.'");</script>';
}

$theme->assign('ban_name', $res['name']);
$theme->assign('ban_reason', $res['reason']);
$theme->assign('ban_authid', trim($res['authid']));
$theme->assign('customreason', ((isset($GLOBALS['config']['bans.customreasons'])&&$GLOBALS['config']['bans.customreasons']!="")?unserialize($GLOBALS['config']['bans.customreasons']):false));

$theme->left_delimiter = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_admin_edit_comms.tpl');
$theme->left_delimiter = "{";
$theme->right_delimiter = "}";
?>
<script type="text/javascript">window.addEvent('domready', function(){
<?php echo $errorScript; ?>
});
function changeReason(szListValue)
{
	$('dreason').style.display = (szListValue == "other" ? "block" : "none");
}
selectLengthTypeReason('<?php echo (int)$res['length']; ?>', '<?php echo (int)$res['type']-1; ?>', '<?php echo addslashes($res['reason']); ?>');
</script>