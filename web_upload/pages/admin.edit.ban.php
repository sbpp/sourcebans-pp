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
	echo '<script>ShowBox("Error", "Possible hacking attempt (URL Key mismatch)!", "red", "index.php?p=admin&c=bans");</script>';
	PageDie();
}
if(!isset($_GET['id']) || !is_numeric($_GET['id']))
{
	echo '<script>ShowBox("Error", "No ban id specified. Please only follow links!", "red", "index.php?p=admin&c=bans");</script>';
	PageDie();
}

$res = $GLOBALS['db']->GetRow("
    				SELECT bid, ba.ip, ba.type, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, ad.gid, CONCAT(se.ip,':',se.port), se.sid, mo.icon, (SELECT origname FROM ".DB_PREFIX."_demos WHERE demtype = 'b' AND demid = {$_GET['id']})
    				FROM ".DB_PREFIX."_bans AS ba
    				LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
    				LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
    				LEFT JOIN ".DB_PREFIX."_mods AS mo ON mo.mid = se.modid
    				WHERE bid = {$_GET['id']}");

if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS)&&(!$userbank->HasAccess(ADMIN_EDIT_OWN_BANS) && $res[8]!=$_COOKIE['user'])&&(!$userbank->HasAccess(ADMIN_EDIT_GROUP_BANS) && $res->fields['gid']!=$userbank->GetProperty('gid')))
{
	echo '<script>ShowBox("Error", "You don\'t have access to this!", "red", "index.php?p=admin&c=bans");</script>';
	PageDie();
}
isset($_GET["page"])?$pagelink = "&page=".$_GET["page"]:$pagelink = "";
if(isset($_POST['name']))
{
	$lengthrev = $GLOBALS['db']->Execute("SELECT length, authid FROM ".DB_PREFIX."_bans WHERE bid = '".(int)$_GET['id']."'");
	$reason = trim($_POST['listReason'] == "other"?$_POST['txtReason']:$_POST['listReason']);
	$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_bans SET
									`name` = ?, `type` = ?, `reason` = ?, `authid` = ?,
									`length` = " . (int)($_POST['banlength']*60) . ",
									`ip` = ?,
									`country` = '',
									`ends` 	 =  `created` + " . (int)($_POST['banlength']*60) . "
									WHERE bid = ?", array($_POST['name'], $_POST['type'], $reason, trim($_POST['steam']), $_POST['ip'], (int)$_GET['id']));
	if(!empty($_POST['dname']))
	{
		$demoid = $GLOBALS['db']->GetRow("SELECT filename FROM `" . DB_PREFIX . "_demos` WHERE demid = '" . $_GET['id'] . "';");
		@unlink(SB_DEMOS."/".$demoid['filename']);
		$edit = $GLOBALS['db']->Execute("REPLACE INTO ".DB_PREFIX."_demos
										(`demid`, `demtype`, `filename`, `origname`)
										VALUES
										(?,
										'b',
										?,
										?)", array((int)$_GET['id'], $_POST['did'], $_POST['dname']));
	}

	if((int)($_POST['banlength']*60) != $lengthrev->fields['length'])
		$log = new CSystemLog("m", "Ban length edited", "Ban length for (" . $lengthrev->fields['authid'] . ") has been updated, before: ".$lengthrev->fields['length'].", now: ".(int)($_POST['banlength']*60));
	echo '<script>ShowBox("Ban updated", "The ban has been updated successfully", "green", "index.php?p=banlist'.$pagelink.'");</script>';
}

if(!$res)
{
	echo '<script>ShowBox("Error", "There was an error getting details. Maybe the ban has been deleted?", "red", "index.php?p=banlist'.$pagelink.'");</script>';
}

$theme->assign('ban_name', $res['name']);
$theme->assign('ban_reason', $res['reason']);
$theme->assign('ban_authid', trim($res['authid']));
$theme->assign('ban_ip', $res[1]);
$theme->assign('ban_demo', (!empty($res[16])?"Uploaded: <b>".$res[16]."</b>":""));
$theme->assign('customreason', ((isset($GLOBALS['config']['bans.customreasons'])&&$GLOBALS['config']['bans.customreasons']!="")?unserialize($GLOBALS['config']['bans.customreasons']):false));

$theme->left_delimiter = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_admin_edit_ban.tpl');
$theme->left_delimiter = "{";
$theme->right_delimiter = "}";
?>
<script>
function changeReason(szListValue)
{
	$('dreason').style.display = (szListValue == "other" ? "block" : "none");
}
selectLengthTypeReason('<?php echo (int)$res['length']; ?>', '<?php echo $res['type']; ?>', '<?php echo addslashes($res['reason']); ?>');
</script>
