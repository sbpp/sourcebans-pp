<?php
/**
 * =============================================================================
 * Protest ban page
 *
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id: page.protest.php 261 2009-05-04 18:54:55Z peace-maker $
 * =============================================================================
 */
global $userbank, $theme;
if($GLOBALS['config']['config.enableprotest']!="1")
{
	CreateRedBox("Error", "This page is disabled. You should not be here.");
	PageDie();
}
if(!defined("IN_SB")){echo "You should not be here. Only follow links!"; die();}
if (!isset($_POST['subprotest']) || $_POST['subprotest'] != 1)
{
	$Type = 0;
	$SteamID = "";
	$IP = "";
	$PlayerName = "";
	$UnbanReason = "";
	$Email = "";
}
else
{
	$Type = $_POST['Type'];
	$SteamID = $_POST['SteamID'];
	$IP = $_POST['IP'];
	$PlayerName = $_POST['PlayerName'];
	$UnbanReason = $_POST['BanReason'];
	$Email = $_POST['EmailAddr'];
	$validsubmit = true;
	$errors = "";
	$BanId = -1;

	if(get_magic_quotes_gpc())
		$UnbanReason = stripslashes($UnbanReason);

	if($Type == 0 && !validate_steam($SteamID))
	{
		$errors .= '* Please type a valid STEAM ID.<br>';
		$validsubmit = false;
	}
	elseif($Type==0)
	{
		$pre = $GLOBALS['db']->Prepare("SELECT bid FROM ".DB_PREFIX."_bans WHERE authid=? AND RemovedBy IS NULL;");
		$res = $GLOBALS['db']->Execute($pre,array($SteamID));
		if ($res->RecordCount() == 0)
		{
			$errors .=  '* That Steam ID is not banned!<br>';
			$validsubmit = false;
		}
		else
		{
			$BanId = (int)$res->fields[0];
			$res = $GLOBALS['db']->Execute("SELECT pid FROM ".DB_PREFIX."_protests WHERE bid=$BanId");
			if ($res->RecordCount() > 0)
			{
				$errors .=  '* A protest is already pending for this Steam ID.<br>';
				$validsubmit = false;
			}
		}
	}
	if($Type == 1 && !validate_ip($IP))
	{
		$errors .= '* Please type a valid IP.<br>';
		$validsubmit = false;
	}
	elseif($Type==1)
	{
		$pre = $GLOBALS['db']->Prepare("SELECT bid FROM ".DB_PREFIX."_bans WHERE ip=? AND RemovedBy IS NULL;");
		$res = $GLOBALS['db']->Execute($pre,array($IP));
		if ($res->RecordCount() == 0)
		{
			$errors .=  '* That IP is not banned!<br>';
			$validsubmit = false;
		}
		else
		{
			$BanId = (int)$res->fields[0];
			$res = $GLOBALS['db']->Execute("SELECT pid FROM ".DB_PREFIX."_protests WHERE bid=$BanId");
			if ($res->RecordCount() > 0)
			{
				$errors .=  '* A protest is already pending for this IP.<br>';
				$validsubmit = false;
			}
		}
	}
	if (strlen($PlayerName) == 0)
	{
		$errors .=  '* You must include a player name<br>';
		$validsubmit = false;
	}
	if (strlen($UnbanReason) == 0)
	{
		$errors .=  '* You must include comments<br>';
		$validsubmit = false;
	}
	if (!check_email($Email))
	{
		$errors .=  '* You must include a valid email address<br>';
		$validsubmit = false;
	}

	if(!$validsubmit)
		CreateRedBox("Error", $errors);

	if ($validsubmit && $BanId != -1)
	{
		$UnbanReason = trim($UnbanReason);
		$pre = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_protests(bid,datesubmitted,reason,email,archiv,pip) VALUES (?,UNIX_TIMESTAMP(),?,?,0,?)");
		$res = $GLOBALS['db']->Execute($pre,array($BanId, $UnbanReason,$Email,$_SERVER['REMOTE_ADDR']));
        $protid = $GLOBALS['db']->Insert_ID();
        $protadmin = $GLOBALS['db']->GetRow("SELECT ad.user FROM ".DB_PREFIX."_protests p, ".DB_PREFIX."_admins ad, ".DB_PREFIX."_bans b WHERE p.pid = '".$protid."' AND b.bid = p.bid AND ad.aid = b.aid");

		$Type = 0;
		$SteamID = "";
		$IP = "";
		$PlayerName = "";
		$UnbanReason = "";
		$Email = "";

		// Send an email when protest was posted
		$headers = 'From: protest@' . $_SERVER['HTTP_HOST'] . "\n" .
		'X-Mailer: PHP/' . phpversion();

		$emailinfo = $GLOBALS['db']->Execute("SELECT aid, user, email FROM `".DB_PREFIX."_admins` WHERE aid = (SELECT aid FROM `".DB_PREFIX."_bans` WHERE bid = '".(int)$BanId."');");
        $requri = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], ".php")+4);
		if(isset($GLOBALS['config']['protest.emailonlyinvolved']) && $GLOBALS['config']['protest.emailonlyinvolved'] == 1 && !empty($emailinfo->fields['email']))
			$admins = array(array('aid' => $emailinfo->fields['aid'], 'user' => $emailinfo->fields['user'], 'email' => $emailinfo->fields['email']));
		else
			$admins = $userbank->GetAllAdmins();
		foreach($admins AS $admin)
		{
			$message = "";
			$message .= "Hello " . $admin['user'] . ",\n\n";
			$message .= "A new ban protest has been posted on your SourceBans page.\n\n";
			$message .= "Player: ".$_POST['PlayerName']." (".$_POST['SteamID'].")\nBanned by: ".$protadmin['user']."\nMessage: ".$_POST['BanReason']."\n\n";
			$message .= "Click the link below to view the current ban protests.\n\nhttp://" . $_SERVER['HTTP_HOST'] . $requri . "?p=admin&c=bans#^1";
			if($userbank->HasAccess(ADMIN_BAN_PROTESTS, $admin['aid']) && $userbank->HasAccess(ADMIN_NOTIFY_PROTEST, $admin['aid']))
				mail($admin['email'], "[SourceBans] Ban Protest Added", $message, $headers);
		}

		CreateGreenBox("Successful", "Your protest has been sent.");
	}
}

$theme->assign('steam_id', $SteamID);
$theme->assign('ip', $IP);
$theme->assign('player_name', $PlayerName);
$theme->assign('reason', $UnbanReason);
$theme->assign('player_email', $Email);

$theme->display('page_protestban.tpl');
?>
<script type="text/javascript">
function changeType(szListValue)
{
	$('steam.row').style.display = (szListValue == "0" ? "" : "none");
	$('ip.row').style.display    = (szListValue == "1" ? "" : "none");
}
$('Type').options[<?php echo $Type; ?>].selected = true;
changeType(<?php echo $Type; ?>);
</script>
