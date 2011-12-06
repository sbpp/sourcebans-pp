<?php
/**
 * =============================================================================
 * Dashboard
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: page.home.php 278 2009-07-07 11:42:36Z tsunami $
 * =============================================================================
 */
global $theme;
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
define('IN_HOME', true);

$res = $GLOBALS['db']->Execute("SELECT count(name) FROM ".DB_PREFIX."_banlog");
$totalstopped = (int)$res->fields[0];

$res = $GLOBALS['db']->Execute("SELECT bl.name, time, bl.sid, bl.bid, b.type, b.authid, b.ip
								FROM ".DB_PREFIX."_banlog AS bl
								LEFT JOIN ".DB_PREFIX."_bans AS b ON b.bid = bl.bid
								ORDER BY time DESC LIMIT 10");

$GLOBALS['server_qry'] = "";
$stopped = array();
$blcount = 0;
while (!$res->EOF)
{
	$info = array();
	$info['date'] = SBDate($dateformat,$res->fields[1]);
	$info['name'] = stripslashes($res->fields[0]);
	$info['short_name'] = trunc($info['name'], 40, false);
	$info['auth'] = $res->fields['authid'];
	$info['ip'] = $res->fields['ip'];
	$info['server'] = "block_".$res->fields['sid']."_$blcount";
	if($res->fields['type'] == 1)
	{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['ip'] . "&advType=ip&Submit";
	}else{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['auth'] . "&advType=steamid&Submit";
	}
	$info['link_url'] = "window.location = '" . $info['search_link'] . "';";
	$info['name'] = htmlspecialchars(addslashes($info['name']), ENT_QUOTES, 'UTF-8');
	$info['popup'] = "ShowBox('Blocked player: " . $info['name'] . "', '" . $info['name'] . " tried to enter<br />' + document.getElementById('".$info['server']."').title + '<br />at " . $info['date'] . "<br /><div align=middle><a href=" . $info['search_link'] . ">Click here for ban details.</a></div>', 'red', '', true);";
		
    $GLOBALS['server_qry'] .= "xajax_ServerHostProperty(".$res->fields['sid'].", 'block_".$res->fields['sid']."_$blcount', 'title', 100);";
        
    array_push($stopped,$info);
	$res->MoveNext();
    ++$blcount;
}

$res = $GLOBALS['db']->Execute("SELECT count(bid) FROM ".DB_PREFIX."_bans");
$BanCount = (int)$res->fields[0];

$res = $GLOBALS['db']->Execute("SELECT bid, ba.ip, ba.authid, ba.name, created, ends, length, reason, ba.aid, ba.sid, ad.user, CONCAT(se.ip,':',se.port), se.sid, mo.icon, ba.RemoveType, ba.type
			    				FROM ".DB_PREFIX."_bans AS ba 
			    				LEFT JOIN ".DB_PREFIX."_admins AS ad ON ba.aid = ad.aid
			    				LEFT JOIN ".DB_PREFIX."_servers AS se ON se.sid = ba.sid
			    				LEFT JOIN ".DB_PREFIX."_mods AS mo ON mo.mid = se.modid
			    				ORDER BY created DESC LIMIT 10");
$bans = array();
while (!$res->EOF)
{
	$info = array();
	$info['name'] = stripslashes($res->fields[3]);
	$info['created'] = SBDate($dateformat,$res->fields['created']);
	$ltemp = explode(",",$res->fields[6] == 0 ? 'Permanent' : SecondsToString(intval($res->fields[6])));
	$info['length'] = $ltemp[0];
	$info['icon'] = empty($res->fields[13]) ? 'web.png' : $res->fields[13];
	$info['authid'] = $res->fields[2];
	$info['ip'] = $res->fields[1];
	if($res->fields[15] == 1)
	{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['ip'] . "&advType=ip&Submit";
	}else{
		$info['search_link'] = "index.php?p=banlist&advSearch=" . $info['authid'] . "&advType=steamid&Submit";
	}
	$info['link_url'] = "window.location = '" . $info['search_link'] . "';";
	$info['short_name'] = trunc($info['name'], 25, false);
	
	if($res->fields[14] == 'D' || $res->fields[14] == 'U' || $res->fields[14] == 'E' || ($res->fields[6] && $res->fields[5] < time()))
	{
		$info['unbanned'] = true;
		
		if($res->fields[14] == 'D')
			$info['ub_reason'] = 'D';
		elseif($res->fields[14] == 'U')
			$info['ub_reason'] = 'U';
		else
			$info['ub_reason'] = 'E';
	}
	else
	{
		$info['unbanned'] = false;
	}
	
	array_push($bans,$info);
	$res->MoveNext();
}


require(TEMPLATES_PATH . "/page.servers.php"); //Set theme vars from servers page

$theme->assign('dashboard_lognopopup', (isset($GLOBALS['config']['dash.lognopopup']) && $GLOBALS['config']['dash.lognopopup'] == "1"));
$theme->assign('dashboard_title',  stripslashes($GLOBALS['config']['dash.intro.title']));
$theme->assign('dashboard_text',  stripslashes($GLOBALS['config']['dash.intro.text']));
$theme->assign('players_blocked', $stopped);
$theme->assign('total_blocked', $totalstopped);

$theme->assign('players_banned', $bans);
$theme->assign('total_bans', $BanCount);

$theme->display('page_dashboard.tpl');
?>
