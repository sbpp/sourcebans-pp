<?php
/**
 * =============================================================================
 * Banlist page
 *
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id: page.banlist.php 292 2009-07-19 19:49:35Z peace-maker $
 * =============================================================================
 */
global $theme;
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
$BansPerPage = SB_BANS_PER_PAGE;
$servers = array();
global $userbank;
function setPostKey()
{
	if(isset($_SERVER['REMOTE_IP']))
		$_SESSION['banlist_postkey'] = md5($_SERVER['REMOTE_IP'].time().rand(0,100000));
	else
		$_SESSION['banlist_postkey'] = md5(time().rand(0,100000));
}
if (!isset($_SESSION['banlist_postkey']) || strlen($_SESSION['banlist_postkey']) < 4)
	setPostKey();

$page = 1;
$pagelink = "";

PruneBans();

if (isset($_GET['page']) && $_GET['page'] > 0)
{
	$page = intval($_GET['page']);
	$pagelink = "&page=".$page;
}
if (version_compare($GLOBALS['db_version'], "5.6.0") >= 0)
{
  $GLOBALS['db']->Execute("set session optimizer_switch='block_nested_loop=off';");
}
if (isset($_GET['a']) && $_GET['a'] == "unban" && isset($_GET['id']))
{
	if ($_GET['key'] != $_SESSION['banlist_postkey'])
		die("Possible hacking attempt (URL Key mismatch)");
	//we have a multiple unban asking
	if(isset($_GET['bulk']))
		$bids = explode(",",$_GET['id']);
	else
		$bids = array($_GET['id']);
	$ucount = 0;
	$fail = 0;
	foreach($bids AS $bid) {
		$bid = intval($bid);
		$res = $GLOBALS['db']->Execute("SELECT a.aid, a.gid FROM `".DB_PREFIX."_bans` b INNER JOIN ".DB_PREFIX."_admins a ON a.aid = b.aid WHERE bid = '".$bid."';");
		if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_UNBAN) &&
		    !($userbank->HasAccess(ADMIN_UNBAN_OWN_BANS) && $res->fields['aid'] == $userbank->GetAid()) &&
		    !($userbank->HasAccess(ADMIN_UNBAN_GROUP_BANS) && $res->fields['gid'] == $userbank->GetProperty('gid')))
		{
			$fail++;
			if(!isset($_GET['bulk']))
				die("You don't have access to this");
			continue;
		}

		$row = $GLOBALS['db']->GetRow("SELECT b.ip, b.authid, 
										b.name, b.created, b.sid, b.type, m.steam_universe, UNIX_TIMESTAMP() as now
										FROM ".DB_PREFIX."_bans b
										LEFT JOIN ".DB_PREFIX."_servers s ON s.sid = b.sid
										LEFT JOIN ".DB_PREFIX."_mods m ON m.mid = s.modid
										WHERE b.bid = ? AND (b.length = '0' OR b.ends > UNIX_TIMESTAMP()) AND b.RemoveType IS NULL",array($bid));
		if(empty($row) || !$row) {
			$fail++;
			if(!isset($_GET['bulk'])) {
				echo "<script>ShowBox('Player Not Unbanned', 'The player was not unbanned, either already unbanned or not a valid ban.', 'red', 'index.php?p=banlist$pagelink');</script>";
				PageDie();
			}
			continue;
		}
		$unbanReason = htmlspecialchars(trim($_GET['ureason']));
		$ins = $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_bans` SET
										`RemovedBy` = ?,
										`RemoveType` = 'U',
										`RemovedOn` = UNIX_TIMESTAMP(),
										`ureason` = ?
										WHERE `bid` = ?;",
										array( $userbank->GetAid(), $unbanReason, $bid));

		$protestsunban = $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_protests` SET archiv = '4' WHERE bid = '".$bid."';");

		$blocked = $GLOBALS['db']->GetAll("SELECT s.sid, m.steam_universe FROM `".DB_PREFIX."_banlog` bl INNER JOIN ".DB_PREFIX."_servers s ON s.sid = bl.sid INNER JOIN ".DB_PREFIX."_mods m ON m.mid = s.modid WHERE bl.bid=? AND (UNIX_TIMESTAMP() - bl.time <= 300)",array($bid));
		foreach($blocked as $tempban)
		{
			SendRconSilent(($row['type']==0?"removeid STEAM_" . $tempban['steam_universe'] . substr($row['authid'], 7):"removeip ".$row['ip']), $tempban['sid']);
		}
		if(((int)$row['now'] - (int)$row['created']) <= 300 && $row['sid'] != "0" && !in_array_dim($row['sid'], $blocked))
			SendRconSilent(($row['type']==0?"removeid STEAM_" . $row['steam_universe'] . substr($row['authid'], 7):"removeip ".$row['ip']), $row['sid']);

		if($res){
			if(!isset($_GET['bulk']))
				echo "<script>ShowBox('Player Unbanned', '".StripQuotes($row['name'])." (" . ($row['type']==0?$row['authid']:$row['ip']) . ") has been unbanned from SourceBans.', 'green', 'index.php?p=banlist$pagelink');</script>";
			$log = new CSystemLog("m", "Player Unbanned", "'".StripQuotes($row['name'])."' (" . ($row['type']==0?$row['authid']:$row['ip']) . ") has been unbanned");
			$ucount++;
		}else{
			if(!isset($_GET['bulk']))
				echo "<script>ShowBox('Player NOT Unbanned', 'There was an error unbanning ".StripQuotes($row['name'])."', 'red', 'index.php?p=banlist$pagelink', true);</script>";
			$fail++;
		}
	}
	if(isset($_GET['bulk']))
		echo "<script>ShowBox('Players Unbanned', '$ucount players has been unbanned from SourceBans.<br>$fail failed.', 'green', 'index.php?p=banlist$pagelink');</script>";
}
else if(isset($_GET['a']) && $_GET['a'] == "delete")
{
	if ($_GET['key'] != $_SESSION['banlist_postkey'])
		die("Possible hacking attempt (URL Key mismatch)");

	if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_BAN))
	{
		echo "<script>ShowBox('Error', 'You do not have access to this.', 'red', 'index.php?p=banlist$pagelink');</script>";
		PageDie();
	}
	//we have a multiple ban delete asking
	if(isset($_GET['bulk']))
		$bids = explode(",",$_GET['id']);
	else
		$bids = array($_GET['id']);
	$dcount = 0;
	$fail = 0;
	foreach($bids AS $bid) {
		$bid = intval($bid);
		$demres = $GLOBALS['db']->Execute("SELECT filename FROM `".DB_PREFIX."_demos` WHERE `demid` = ?",
									array( $bid ));
		@unlink(SB_DEMOS."/".$demres->fields["filename"]);
		$blocked = $GLOBALS['db']->GetAll("SELECT s.sid, m.steam_universe FROM `".DB_PREFIX."_banlog` bl INNER JOIN ".DB_PREFIX."_servers s ON s.sid = bl.sid INNER JOIN ".DB_PREFIX."_mods m ON m.mid = s.modid WHERE bl.bid=? AND (UNIX_TIMESTAMP() - bl.time <= 300)",array($bid));
		$steam = $GLOBALS['db']->GetRow("SELECT b.name, b.authid, b.created, b.sid, b.RemoveType, b.ip, b.type, m.steam_universe, UNIX_TIMESTAMP() AS now
										FROM ".DB_PREFIX."_bans b 
										LEFT JOIN ".DB_PREFIX."_servers s ON s.sid = b.sid
										LEFT JOIN ".DB_PREFIX."_mods m ON m.mid = s.modid 
										WHERE b.bid=?",array($bid));
		$block = $GLOBALS['db']->Execute("DELETE FROM `".DB_PREFIX."_banlog` WHERE bid = ?",array($bid));
		$res = $GLOBALS['db']->Execute("DELETE FROM `".DB_PREFIX."_bans` WHERE `bid` = ?",
									array( $bid ));
		if(empty($steam['RemoveType']))
		{
			foreach($blocked as $tempban)
			{
				SendRconSilent(($steam['type']==0?"removeid STEAM_" . $tempban['steam_universe'] . substr($steam['authid'], 7):"removeip ".$steam['ip']), $tempban['sid']);
			}
			if(((int)$steam['now'] - (int)$steam['created']) <= 300 && $steam['sid'] != "0" && !in_array_dim($steam['sid'], $blocked))
				SendRconSilent(($steam['type']==0?"removeid STEAM_" . $steam['steam_universe'] . substr($steam['authid'], 7):"removeip ".$steam['ip']), $steam['sid']);
		}

		if($res){
			if(!isset($_GET['bulk']))
				echo "<script>ShowBox('Ban Deleted', 'The ban for \'".StripQuotes($steam['name'])."\' (".($steam['type']==0?$steam['authid']:$steam['ip']).") has been deleted from SourceBans', 'green', 'index.php?p=banlist$pagelink');</script>";
			$log = new CSystemLog("m", "Ban Deleted", "Ban '".StripQuotes($steam['name'])."' (" . ($steam['type']==0?$steam['authid']:$steam['ip']) . ") has been deleted.");
			$dcount++;
		}else{
			if(!isset($_GET['bulk']))
				echo "<script>ShowBox('Ban NOT Deleted', 'The ban for \'".StripQuotes($steam['name'])."\' had an error while being removed.', 'red', 'index.php?p=banlist$pagelink', true);</script>";
			$fail++;
		}
	}
	if(isset($_GET['bulk']))
		echo "<script>ShowBox('Players Deleted', '$dcount players has been deleted from SourceBans.<br>$fail failed.', 'green', 'index.php?p=banlist$pagelink');</script>";
}

$BansStart = intval(($page-1) * $BansPerPage);
$BansEnd = intval($BansStart+$BansPerPage);

// hide inactive bans feature
if(isset($_GET["hideinactive"]) && $_GET["hideinactive"] == "true") {// hide
	$_SESSION["hideinactive"] = true;
	//ShowBox('Hide inactive bans', 'Inactive bans will be hidden from the banlist.', 'green', 'index.php?p=banlist', true);
} elseif(isset($_GET["hideinactive"]) && $_GET["hideinactive"] == "false") { // show
	unset($_SESSION["hideinactive"]);
	//ShowBox('Show inactive bans', 'Inactive bans will be shown in the banlist.', 'green', 'index.php?p=banlist', true);
}
if(isset($_SESSION["hideinactive"])) {
	$hidetext = "Show";
	$hideinactive = " AND RemoveType IS NULL";
	$hideinactiven = " WHERE RemoveType IS NULL";
} else {
	$hidetext = "Hide";
	$hideinactive = "";
	$hideinactiven = "";
}


if (isset($_GET['searchText']))
{
	$search = '%'.trim($_GET['searchText']).'%';
    
    // disable ip search if hiding player ips
    $search_ips = "";
	$search_array = array();
    if(!isset($GLOBALS['config']['banlist.hideplayerips']) || $GLOBALS['config']['banlist.hideplayerips'] != "1" || $userbank->is_admin())
	{
        $search_ips = "BA.ip LIKE ? OR ";
		$search_array[] = $search;
	}
	
	$res = $GLOBALS['db']->Execute(
	"SELECT BA.bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM ".DB_PREFIX."_demos as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
			(SELECT count(*) FROM ".DB_PREFIX."_bans as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL) OR (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL)) as history_count
	   FROM ".DB_PREFIX."_bans AS BA
  LEFT JOIN ".DB_PREFIX."_servers AS SE ON SE.sid = BA.sid
  LEFT JOIN ".DB_PREFIX."_mods AS MO on SE.modid = MO.mid
  LEFT JOIN ".DB_PREFIX."_admins AS AD ON BA.aid = AD.aid
      WHERE ".$search_ips."BA.authid LIKE ? or BA.name LIKE ? or BA.reason LIKE ?" . $hideinactive."
   ORDER BY BA.created DESC
   LIMIT ?,?",array_merge($search_array, array($search,$search,$search,intval($BansStart),intval($BansPerPage))));


	$res_count = $GLOBALS['db']->Execute("SELECT count(BA.bid) FROM ".DB_PREFIX."_bans AS BA WHERE ".$search_ips."BA.authid LIKE ? OR BA.name LIKE ? OR BA.reason LIKE ?" . $hideinactive
										,array_merge($search_array, array($search,$search,$search)));
$searchlink = "&searchText=".$_GET["searchText"];
}
elseif(!isset($_GET['advSearch']))
{
	$res = $GLOBALS['db']->Execute(
	"SELECT bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM ".DB_PREFIX."_demos as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
			(SELECT count(*) FROM ".DB_PREFIX."_bans as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL) OR (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL)) as history_count
	   FROM ".DB_PREFIX."_bans AS BA
  LEFT JOIN ".DB_PREFIX."_servers AS SE ON SE.sid = BA.sid
  LEFT JOIN ".DB_PREFIX."_mods AS MO on SE.modid = MO.mid
  LEFT JOIN ".DB_PREFIX."_admins AS AD ON BA.aid = AD.aid
  ".$hideinactiven."
   ORDER BY created DESC
   LIMIT ?,?",
	array(intval($BansStart),intval($BansPerPage)));

	$res_count = $GLOBALS['db']->Execute("SELECT count(bid) FROM ".DB_PREFIX."_bans".$hideinactiven);
	$searchlink = "";
}

$advcrit = array();
if(isset($_GET['advSearch']))
{
	$value = trim($_GET['advSearch']);
	$type = $_GET['advType'];
	switch($type)
	{
		case "name":
			$where = "WHERE BA.name LIKE ?";
			$advcrit = array("%$value%");
		break;
		case "banid":
			$where = "WHERE BA.bid = ?";
			$advcrit = array($value);
		break;
		case "steamid":
			$where = "WHERE BA.authid = ?";
			$advcrit = array($value);
		break;
		case "steam":
			$where = "WHERE BA.authid LIKE ?";
			$advcrit = array("%$value%");
		break;
		case "ip":
            // disable ip search if hiding player ips
            if(isset($GLOBALS['config']['banlist.hideplayerips']) && $GLOBALS['config']['banlist.hideplayerips'] == "1" && !$userbank->is_admin())
            {
                $where = "";
				$advcrit = array();
			}
			else
			{
				$where = "WHERE BA.ip LIKE ?";
				$advcrit = array("%$value%");
			}
		break;
		case "reason":
			$where = "WHERE BA.reason LIKE ?";
			$advcrit = array("%$value%");
		break;
		case "date":
			$date = explode(",", $value);
			$time = mktime(0,0,0,$date[1],$date[0],$date[2]);
			$time2 = mktime(23,59,59,$date[1],$date[0],$date[2]);
			$where = "WHERE BA.created > ? AND BA.created < ?";
			$advcrit = array($time, $time2);
		break;
		case "length":
			$len = explode(",", $value);
			$length_type = $len[0];
			$length = $len[1]*60;
			$where = "WHERE BA.length ";
			switch($length_type) {
				case "e":
					$where .= "=";
				break;
				case "h":
					$where .= ">";
				break;
				case "l":
					$where .= "<";
				break;
				case "eh":
					$where .= ">=";
				break;
				case "el":
					$where .= "<=";
				break;
			}
			$where .= " ?";
			$advcrit = array($length);
		break;
		case "btype":
			$where = "WHERE BA.type = ?";
			$advcrit = array($value);
		break;
		case "admin":
            if($GLOBALS['config']['banlist.hideadminname']&&!$userbank->is_admin())
			{
                $where = "";
				$advcrit = array();
			}
            else {
                $where = "WHERE BA.aid=?";
                $advcrit = array($value);
            }
		break;
		case "where_banned":
			$where = "WHERE BA.sid=?";
			$advcrit = array($value);
		break;
		case "nodemo":
			$where = "WHERE BA.aid = ? AND NOT EXISTS (SELECT DM.demid FROM ".DB_PREFIX."_demos AS DM WHERE DM.demid = BA.bid)";
			$advcrit = array($value);
		break;
		case "bid":
			$where = "WHERE BA.bid = ?";
			$advcrit = array($value);
		break;
		case "comment":
			if($userbank->is_admin())
			{
				$where = "WHERE CO.type = 'B' AND CO.commenttxt LIKE ?";
				$advcrit = array("%$value%");
			}
			else
			{
                $where = "";
				$advcrit = array();
			}
		break;
		default:
			$where = "";
			$_GET['advType'] = "";
			$_GET['advSearch'] = "";
			$advcrit = array();
		break;
	}
	
	// Make sure we got a "WHERE" clause there, if we add the hide inactive condition
	if(empty($where) && isset($_SESSION["hideinactive"]))
	{
		$hideinactive = $hideinactiven;
	}
	
		$res = $GLOBALS['db']->Execute(
				    	"SELECT BA.bid ban_id, BA.type, BA.ip ban_ip, BA.authid, BA.name player_name, created ban_created, ends ban_ends, length ban_length, reason ban_reason, BA.ureason unban_reason, BA.aid, AD.gid AS gid, adminIp, BA.sid ban_server, country ban_country, RemovedOn, RemovedBy, RemoveType row_type,
			SE.ip server_ip, AD.user admin_name, AD.gid, MO.icon as mod_icon,
			CAST(MID(BA.authid, 9, 1) AS UNSIGNED) + CAST('76561197960265728' AS UNSIGNED) + CAST(MID(BA.authid, 11, 10) * 2 AS UNSIGNED) AS community_id,
			(SELECT count(*) FROM ".DB_PREFIX."_demos as DM WHERE DM.demtype='B' and DM.demid = BA.bid) as demo_count,
			(SELECT count(*) FROM ".DB_PREFIX."_bans as BH WHERE (BH.type = BA.type AND BH.type = 0 AND BH.authid = BA.authid AND BH.authid != '' AND BH.authid IS NOT NULL) OR (BH.type = BA.type AND BH.type = 1 AND BH.ip = BA.ip AND BH.ip != '' AND BH.ip IS NOT NULL)) as history_count
	   FROM ".DB_PREFIX."_bans AS BA
  LEFT JOIN ".DB_PREFIX."_servers AS SE ON SE.sid = BA.sid
  LEFT JOIN ".DB_PREFIX."_mods AS MO on SE.modid = MO.mid
  LEFT JOIN ".DB_PREFIX."_admins AS AD ON BA.aid = AD.aid
  ".($type=="comment"&&$userbank->is_admin()?"LEFT JOIN ".DB_PREFIX."_comments AS CO ON BA.bid = CO.bid":"")."
      ".$where.$hideinactive."
   ORDER BY BA.created DESC
   LIMIT ?,?", array_merge($advcrit, array(intval($BansStart),intval($BansPerPage))));

	$res_count = $GLOBALS['db']->Execute("SELECT count(BA.bid) FROM ".DB_PREFIX."_bans AS BA
										  ".($type=="comment"&&$userbank->is_admin()?"LEFT JOIN ".DB_PREFIX."_comments AS CO ON BA.bid = CO.bid":"")." ".$where.$hideinactive, $advcrit);
	$searchlink = "&advSearch=".$_GET['advSearch']."&advType=".$_GET['advType'];
}

$BanCount = $res_count->fields[0];
if ($BansEnd > $BanCount) $BansEnd = $BanCount;
if (!$res)
{
	echo "No Bans Found.";
	PageDie();
}

$view_comments = false;
$bans = array();
while (!$res->EOF)
{
	$data = array();

	$data['ban_id'] = $res->fields['ban_id'];

	if(!empty($res->fields['ban_ip']) )
	{
		if(!empty($res->fields['ban_country']) && $res->fields['ban_country'] != ' ')
		{
			$data['country'] = '<img src="images/country/' .strtolower($res->fields['ban_country']) . '.gif" alt="' . $res->fields['ban_country'] . '" border="0" align="absmiddle" />';
	    }
		elseif(isset($GLOBALS['config']['banlist.nocountryfetch']) && $GLOBALS['config']['banlist.nocountryfetch'] == "0")
		{
			$country = FetchIp($res->fields['ban_ip']);
			$edit = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_bans SET country = ?
				                            WHERE bid = ?",array($country,$res->fields['ban_id']));

			$data['country'] = '<img src="images/country/' . strtolower($country) . '.gif" alt="' . $country . '" border="0" align="absmiddle" />';
		}
		else
		{
			$data['country'] = '<img src="images/country/zz.gif" alt="Unknown Country" border="0" align="absmiddle" />';
		}
	}
	else
	{
		$data['country'] = '<img src="images/country/zz.gif" alt="Unknown Country" border="0" align="absmiddle" />';
	}

	$data['ban_date'] = SBDate($dateformat,$res->fields['ban_created']);
	$data['player'] = addslashes($res->fields['player_name']);
	$data['type'] = $res->fields['type'];
	$data['steamid'] = $res->fields['authid'];
	$data['communityid'] = $res->fields['community_id'];

	if(isset($GLOBALS['config']['banlist.hideadminname']) && $GLOBALS['config']['banlist.hideadminname'] == "1" && !$userbank->is_admin())
		$data['admin'] = false;
	else
		$data['admin'] = stripslashes($res->fields['admin_name']);
	$data['reason'] = stripslashes($res->fields['ban_reason']);
	$data['ban_length'] = $res->fields['ban_length'] == 0 ? 'Permanent' : SecondsToString(intval($res->fields['ban_length']));

// Custom "listtable_1_banned" & "listtable_1_permanent" addition entries
// Comment the 14 lines below out if they cause issues
	if ($res->fields['ban_length'] == 0)
	{
		$data['expires'] = 'never';
		$data['class'] = "listtable_1_permanent";
		$data['ub_reason'] = "";
		$data['unbanned'] = false;
	}
	else
	{
		$data['expires'] = SBDate($dateformat,$res->fields['ban_ends']);
		$data['class'] = "listtable_1_banned";
		$data['ub_reason'] = "";
		$data['unbanned'] = false;
	}
// End custom entries

	if($res->fields['row_type'] == 'D' || $res->fields['row_type'] == 'U' || $res->fields['row_type'] == 'E' || ($res->fields['ban_length'] && $res->fields['ban_ends'] < time()))
	{
		$data['unbanned'] = true;
		$data['class'] = "listtable_1_unbanned";

		if($res->fields['row_type'] == "D")
			$data['ub_reason'] = "(Deleted)";
		elseif($res->fields['row_type'] == "U")
			$data['ub_reason'] = "(Unbanned)";
		else
			$data['ub_reason'] = "(Expired)";

		$data['ureason'] = stripslashes($res->fields['unban_reason']);

		$removedby = $GLOBALS['db']->GetRow("SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = '".$res->fields['RemovedBy']."'");
        $data['removedby'] = "";
        if(isset($removedby[0]))
            $data['removedby'] = $removedby[0];
	}
// Don't need this stuff.
// Uncomment below if the modifications above cause issues
//	else
//	{
//		$data['unbanned'] = false;
//		$data['class'] = "listtable_1";
//		$data['ub_reason'] = "";
//	}

	$data['layer_id'] = 'layer_'.$res->fields['ban_id'];
	if($data['type'] == "0")
		$alrdybnd = $GLOBALS['db']->Execute("SELECT count(bid) as count FROM `".DB_PREFIX."_bans` WHERE authid = '".$data['steamid']."' AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '0';");
	else
		$alrdybnd = $GLOBALS['db']->Execute("SELECT count(bid) as count FROM `".DB_PREFIX."_bans` WHERE ip = '".$res->fields['ban_ip']."' AND (length = 0 OR ends > UNIX_TIMESTAMP()) AND RemovedBy IS NULL AND type = '1';");
	if($alrdybnd->fields['count']==0)
		$data['reban_link'] = CreateLinkR('<img src="images/forbidden.png" border="0" alt="" style="vertical-align:middle" /> Reban',"index.php?p=admin&c=bans".$pagelink."&rebanid=".$res->fields['ban_id']."&key=".$_SESSION['banlist_postkey']."#^0");
	else
		$data['reban_link'] = false;
	$data['blockcomm_link'] = CreateLinkR('<img src="images/forbidden.png" border="0" alt="" style="vertical-align:middle" /> Block Comms',"index.php?p=admin&c=comms".$pagelink."&blockfromban=".$res->fields['ban_id']."&key=".$_SESSION['banlist_postkey']."#^0");
	$data['details_link'] = CreateLinkR('click','getdemo.php?type=B&id='.$res->fields['ban_id']);
	$data['groups_link'] = CreateLinkR('<img src="images/groups.png" border="0" alt="" style="vertical-align:middle" /> Show Groups',"index.php?p=admin&c=bans&fid=".$data['communityid']."#^4");
	$data['friend_ban_link'] = CreateLinkR('<img src="images/group_delete.png" border="0" alt="" style="vertical-align:middle" /> Ban Friends', '#', '', '_self', false, "BanFriendsProcess('".$data['communityid']."','".StripQuotes($data['player'])."');return false;");
	$data['edit_link'] = CreateLinkR('<img src="images/edit.gif" border="0" alt="" style="vertical-align:middle" /> Edit Details',"index.php?p=admin&c=bans&o=edit".$pagelink."&id=".$res->fields['ban_id']."&key=".$_SESSION['banlist_postkey']);

	$data['unban_link'] = CreateLinkR('<img src="images/locked.gif" border="0" alt="" style="vertical-align:middle" /> Unban',"#","", "_self", false, "UnbanBan('".$res->fields['ban_id']."', '".$_SESSION['banlist_postkey']."', '".$pagelink."', '".StripQuotes($data['player'])."', 1, false);return false;");
	$data['delete_link'] = CreateLinkR('<img src="images/delete.gif" border="0" alt="" style="vertical-align:middle" /> Delete Ban',"#","", "_self", false, "RemoveBan('".$res->fields['ban_id']."', '".$_SESSION['banlist_postkey']."', '".$pagelink."', '".StripQuotes($data['player'])."', 0, false);return false;");

	
	$data['server_id'] = $res->fields['ban_server'];

	if(empty($res->fields['mod_icon']))
	{
		$modicon = "web.png";
	}
	else
	{
		$modicon = $res->fields['mod_icon'];
	}

	$data['mod_icon'] = '<img src="images/games/' .$modicon . '" alt="MOD" border="0" align="absmiddle" />&nbsp;' . $data['country'];

    if($res->fields['history_count'] > 1)
        $data['prevoff_link'] = $res->fields['history_count'] . " " . CreateLinkR("(search)","index.php?p=banlist&searchText=" . ($data['type']==0?$data['steamid']:$res->fields['ban_ip']) . "&Submit");
    else
        $data['prevoff_link'] = "No previous bans";



	if (strlen($res->fields['ban_ip']) < 7)
		$data['ip'] = 'none';
	else
		$data['ip'] =  $data['country'] . '&nbsp;' . $res->fields['ban_ip'];

	if ($res->fields['ban_length'] == 0)
		$data['expires'] = 'never';
	else
		$data['expires'] = SBDate($dateformat,$res->fields['ban_ends']);


	if ($res->fields['demo_count'] == 0)
	{
		$data['demo_available'] = false;
		$data['demo_quick'] = 'N/A';
		$data['demo_link'] = CreateLinkR('<img src="images/demo.gif" border="0" alt="" style="vertical-align:middle" /> No Demos',"#");
	}
	else
	{
		$data['demo_available'] = true;
		$data['demo_quick'] = CreateLinkR('Demo',"getdemo.php?type=B&id=".$data['ban_id']);
		$data['demo_link'] = CreateLinkR('<img src="images/demo.gif" border="0" alt="" style="vertical-align:middle" /> Review Demo',"getdemo.php?type=B&id=".$data['ban_id']);
	}



	$data['server_id'] = $res->fields['ban_server'];

	$banlog = $GLOBALS['db']->GetAll("SELECT bl.time, bl.name, s.ip, s.port FROM `".DB_PREFIX."_banlog` AS bl LEFT JOIN `".DB_PREFIX."_servers` AS s ON s.sid = bl.sid WHERE bid = '".$data['ban_id']."'");
	$data['blockcount'] = sizeof($banlog);
	$logstring = "";
	foreach($banlog AS $logged) {
		if(!empty($logstring))
			$logstring .= ", ";
		$logstring .= '<span title="Server: '.$logged["ip"].':'.$logged["port"].', Date: '.SBDate($dateformat,$logged["time"]).'">'.($logged["name"]!=""?htmlspecialchars($logged["name"]):"<i>no name</i>").'</span>';
	}
	$data['banlog'] = $logstring;

	//COMMENT STUFF
	//-----------------------------------
	if($userbank->is_admin()) {
		$view_comments = true;
		$commentres = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
											FROM `".DB_PREFIX."_comments` AS C
											WHERE type = 'B' AND bid = '".$data['ban_id']."' ORDER BY added desc");

		if($commentres->RecordCount()>0) {
			$comment = array();
			$morecom = 0;
			while(!$commentres->EOF) {
				$cdata = array();
				$cdata['morecom'] = ($morecom==1?true:false);
				if($commentres->fields['aid'] == $userbank->GetAid() || $userbank->HasAccess(ADMIN_OWNER)) {
					$cdata['editcomlink'] = CreateLinkR('<img src=\'images/edit.gif\' border=\'0\' alt=\'\' style=\'vertical-align:middle\' />','index.php?p=banlist&comment='.$data['ban_id'].'&ctype=B&cid='.$commentres->fields['cid'].$pagelink,'Edit Comment');
					if($userbank->HasAccess(ADMIN_OWNER)) {
						$cdata['delcomlink'] = "<a href=\"#\" class=\"tip\" title=\"<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Delete Comment\" target=\"_self\" onclick=\"RemoveComment(".$commentres->fields['cid'].",'B',".(isset($_GET["page"])?$page:-1).");\"><img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /></a>";
					}
				}
				else {
					$cdata['editcomlink'] = "";
					$cdata['delcomlink'] = "";
				}

				$cdata['comname'] = $commentres->fields['comname'];
				$cdata['added'] = SBDate($dateformat, $commentres->fields['added']);
				$cdata['commenttxt'] = htmlspecialchars($commentres->fields['commenttxt']);
				$cdata['commenttxt'] = str_replace("\n", "<br />", $cdata['commenttxt']);
				// Parse links and wrap them in a <a href=""></a> tag to be easily clickable
				$cdata['commenttxt'] = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $cdata['commenttxt']);

				if(!empty($commentres->fields['edittime'])) {
					$cdata['edittime'] = SBDate($dateformat, $commentres->fields['edittime']);
					$cdata['editname'] = $commentres->fields['editname'];
				}
				else {
					$cdata['edittime'] = "";
					$cdata['editname'] = "";
				}

				$morecom = 1;
				array_push($comment,$cdata);
				$commentres->MoveNext();
			}
		}
		else
			$comment = "None";

		$data['commentdata'] = $comment;
	}


	$data['addcomment'] = CreateLinkR('<img src="images/details.gif" border="0" alt="" style="vertical-align:middle" /> Add Comment','index.php?p=banlist&comment='.$data['ban_id'].'&ctype=B'.$pagelink);
	//-----------------------------------

	$data['ub_reason'] = (isset($data['ub_reason'])?$data['ub_reason']:"");
 	$data['banlength'] = $data['ban_length'] . " " .  $data['ub_reason'];
	$data['view_edit'] = ($userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS) || ($userbank->HasAccess(ADMIN_EDIT_OWN_BANS) && $res->fields['aid']==$userbank->GetAid()) || ($userbank->HasAccess(ADMIN_EDIT_GROUP_BANS) && $res->fields['gid']==$userbank->GetProperty('gid')));
    $data['view_unban'] = ($userbank->HasAccess(ADMIN_OWNER|ADMIN_UNBAN) || ($userbank->HasAccess(ADMIN_UNBAN_OWN_BANS) && $res->fields['aid']==$userbank->GetAid()) || ($userbank->HasAccess(ADMIN_UNBAN_GROUP_BANS) && $res->fields['gid']==$userbank->GetProperty('gid')));
    $data['view_delete'] = ($userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_BAN));
	array_push($bans,$data);
	$res->MoveNext();
}

if(isset($_GET['advSearch']))
	$advSearchString = "&advSearch=" . (isset($_GET['advSearch'])?$_GET['advSearch']:'') . "&advType=" . (isset($_GET['advType'])?$_GET['advType']:'');
else
	$advSearchString = '';

if ($page > 1)
{
	if(isset($_GET['c']) && $_GET['c'] == "bans")
		$prev = CreateLinkR('<img border="0" alt="prev" src="images/left.gif" style="vertical-align:middle;" /> prev',"javascript:void(0);", "", "_self", false, $prev);
	else
		$prev = CreateLinkR('<img border="0" alt="prev" src="images/left.gif" style="vertical-align:middle;" /> prev',"index.php?p=banlist&page=".($page-1).(isset($_GET['searchText']) > 0?"&searchText=".$_GET['searchText']:'' . $advSearchString));
}
else
{
	$prev = "";
}
if ($BansEnd < $BanCount)
{
	if(isset($_GET['c']) && $_GET['c'] == "bans")
	{
		if(!isset($nxt))
			$nxt = "";
			$next = CreateLinkR('next <img border="0" alt="next" src="images/right.gif" style="vertical-align:middle;" />',"javascript:void(0);", "", "_self", false, $nxt);
	}
	else
		$next = CreateLinkR('next <img border="0" alt="next" src="images/right.gif" style="vertical-align:middle;" />',"index.php?p=banlist&page=".($page+1).(isset($_GET['searchText']) ?"&searchText=".$_GET['searchText']:'' . $advSearchString));
}
else
	$next = "";

//=================[ Start Layout ]==================================
$ban_nav = 'displaying&nbsp;'.$BansStart.'&nbsp;-&nbsp;'.$BansEnd.'&nbsp;of&nbsp;'.$BanCount.'&nbsp;results';

if (strlen($prev) > 0)
{
	$ban_nav .= ' | <b>'.$prev.'</b>';
}
if (strlen($next) > 0)
{
	$ban_nav .= ' | <b>'.$next.'</b>';
}
$pages = ceil($BanCount/$BansPerPage);
if($pages > 1) {
	$ban_nav .= '&nbsp;<select onchange="changePage(this,\'B\',\''.(isset($_GET['advSearch']) ? $_GET['advSearch'] : '').'\',\''.(isset($_GET['advType']) ? $_GET['advType'] : '').'\');">';
	for($i=1;$i<=$pages;$i++)
	{
		if(isset($_GET["page"]) && $i == $page) {
			$ban_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
			continue;
		}
		$ban_nav .= '<option value="' . $i . '">' . $i . '</option>';
	}
	$ban_nav .= '</select>';
}

//COMMENT STUFF
//----------------------------------------
if(isset($_GET["comment"])) {
	$_GET["comment"] = (int)$_GET["comment"];
	$theme->assign('commenttype', (isset($_GET["cid"])?"Edit":"Add"));
	if(isset($_GET["cid"])) {
		$_GET["cid"] = (int)$_GET["cid"];
		$ceditdata = $GLOBALS['db']->GetRow("SELECT * FROM ".DB_PREFIX."_comments WHERE cid = '".$_GET["cid"]."'");
		$ctext = htmlspecialchars($ceditdata['commenttxt']);
		$cotherdataedit = " AND cid != '".$_GET["cid"]."'";
	}
	else 
	{
		$cotherdataedit = "";
		$ctext = "";
	}
	
	$_GET["ctype"] = substr($_GET["ctype"], 0, 1);
	
	$cotherdata = $GLOBALS['db']->Execute("SELECT cid, aid, commenttxt, added, edittime,
											(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.aid) AS comname,
											(SELECT user FROM `".DB_PREFIX."_admins` WHERE aid = C.editaid) AS editname
											FROM `".DB_PREFIX."_comments` AS C
											WHERE type = ? AND bid = ?".$cotherdataedit." ORDER BY added desc", array($_GET["ctype"], $_GET["comment"]));

	$ocomments = array();
	while(!$cotherdata->EOF)
	{
		$coment = array();
		$coment['comname'] = $cotherdata->fields['comname'];
		$coment['added'] = SBDate($dateformat, $cotherdata->fields['added']);
		$coment['commenttxt'] = htmlspecialchars($cotherdata->fields['commenttxt']);
		$coment['commenttxt'] = str_replace("\n", "<br />", $coment['commenttxt']);
		// Parse links and wrap them in a <a href=""></a> tag to be easily clickable
		$coment['commenttxt'] = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1" target="_blank">$1</a>', $coment['commenttxt']);
		if($cotherdata->fields['editname']!="") {
			$coment['edittime'] = SBDate($dateformat, $cotherdata->fields['edittime']);
			$coment['editname'] = $cotherdata->fields['editname'];
		}
		else {
			$coment['editname'] = "";
			$coment['edittime'] = "";
		}
		array_push($ocomments,$coment);
		$cotherdata->MoveNext();
	}

	$theme->assign('page', (isset($_GET["page"])?$page:-1));
	$theme->assign('othercomments', $ocomments);
	$theme->assign('commenttext', (isset($ctext)?$ctext:""));
	$theme->assign('ctype', $_GET["ctype"]);
	$theme->assign('cid', (isset($_GET["cid"])?$_GET["cid"]:""));
}
$theme->assign('view_comments',$view_comments);
$theme->assign('comment', (isset($_GET["comment"])&&$view_comments?$_GET["comment"]:false));
//----------------------------------------

unset($_SESSION['CountryFetchHndl']);

$theme->assign('searchlink', $searchlink);
$theme->assign('hidetext', $hidetext);
$theme->assign('total_bans', $BanCount);
$theme->assign('active_bans', $BanCount);

$theme->assign('ban_nav', $ban_nav);
$theme->assign('ban_list', $bans);
$theme->assign('admin_nick', $userbank->GetProperty("user"));

$theme->assign('admin_postkey', $_SESSION['banlist_postkey']);
$theme->assign('hideplayerips', (isset($GLOBALS['config']['banlist.hideplayerips']) && $GLOBALS['config']['banlist.hideplayerips'] == "1" && !$userbank->is_admin()));
$theme->assign('hideadminname', (isset($GLOBALS['config']['banlist.hideadminname']) && $GLOBALS['config']['banlist.hideadminname'] == "1" && !$userbank->is_admin()));
$theme->assign('groupban', ($GLOBALS['config']['config.enablegroupbanning']==1 && $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)));
$theme->assign('friendsban', ($GLOBALS['config']['config.enablefriendsbanning']==1 && $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN)));
$theme->assign('general_unban', $userbank->HasAccess(ADMIN_OWNER|ADMIN_UNBAN|ADMIN_UNBAN_OWN_BANS|ADMIN_UNBAN_GROUP_BANS));
$theme->assign('can_delete', $userbank->HasAccess(ADMIN_DELETE_BAN));
$theme->assign('view_bans', ($userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ALL_BANS|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_UNBAN|ADMIN_UNBAN_OWN_BANS|ADMIN_UNBAN_GROUP_BANS|ADMIN_DELETE_BAN)));
$theme->assign('can_export',($userbank->HasAccess(ADMIN_OWNER) || (isset($GLOBALS['config']['config.exportpublic']) && $GLOBALS['config']['config.exportpublic'] == "1")));
$theme->display('page_bans.tpl');
?>
