<?php
/**
 * system-functions.php
 * 
 * This file contains most of our main funcs
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SteamFriends (www.steamfriends.com)
 * @package SourceBans
 * @link http://www.sourcebans.net
 */

/**
* Extended substr function. If it finds mbstring extension it will use, else 
* it will use old substr() function
*
* @param string $string String that need to be fixed
* @param integer $start Start extracting from
* @param integer $length Extract number of characters
* @return string
*/

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}

function substr_utf($string, $start = 0, $length = null) {
$start = (integer) $start >= 0 ? (integer) $start : 0;
if(is_null($length)) 
	$length = strlen_utf($string) - $start;
    return substr($string, $start, $length); 
} 

/**
* Equivalent to htmlspecialchars(), but allows &#[0-9]+ (for unicode)
* This function was taken from punBB codebase <http://www.punbb.org/>
*
* @param string $str
* @return string
*/
function clean($str) {
	$str = preg_replace('/&(?!#[0-9]+;)/s', '&amp;', $str);
	$str = str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $str);
	return $str;
}

/**
* Check if selected email has valid email format
*
* @param string $user_email Email address
* @return boolean
*/
function is_valid_email($user_email) {
	$chars = EMAIL_FORMAT;
	if(strstr($user_email, '@') && strstr($user_email, '.')) {
		return (boolean) preg_match($chars, $user_email);
	}else{
		return false;
	}
}

/**
 * Returns the full location that the website is running in
 *
 * @return string location of SourceBans
 */
function GetLocation()
{
	return substr($_SERVER['SCRIPT_FILENAME'], 0, strlen($base)-strlen("index.php"));
}

/**
 * Displays the header of SourceBans
 *
 * @return noreturn
 */
function BuildPageHeader()
{
	include TEMPLATES_PATH . "/header.php";
}

/**
 * Displays the sub-nav menu of SourceBans
 *
 * @return noreturn
 */
function BuildSubMenu()
{
	include TEMPLATES_PATH . "/submenu.php";
}

/**
 * Displays the content header
 *
 * @return noreturn
 */
function BuildContHeader()
{
	if(!isset($_GET['s']))
	{
		$page = "<b>".(isset($GLOBALS['pagetitle'])?$GLOBALS['pagetitle']:'')."</b>";
	}
	include TEMPLATES_PATH . "/content.header.php";
}


/**
 * Adds a tab to the page
 *
 * @param string $title The title of the tab
 * @param string $utl The link of the tab
 * @param boolean $active Is the tab active?
 * @return noreturn
 */

function AddTab($title, $url, $desc, $active=false)
{
	global $tabs;
	$tab_arr = array(	);
	$tab_arr[0] = "Dashboard";
	$tab_arr[1] = "Ban List";
	$tab_arr[2] = "Servers";
	$tab_arr[3] = "Submit a ban";
	$tab_arr[4] = "Protest a ban";
	$tabs = array();
	$tabs['title'] = $title;
	$tabs['url'] = $url;
	$tabs['desc'] = $desc;
	if(!isset($_GET['p']) && $title == $tab_arr[isset($GLOBALS['config'])?intval($GLOBALS['config']['config.defaultpage']):0])
	{
		$tabs['active'] = true;
		$GLOBALS['pagetitle'] = $title;
	}
	else 
	{
		if(isset($_GET['p']) && substr($url, 3) == $_GET['p'])
		{
			$tabs['active'] = true;
			$GLOBALS['pagetitle'] = $title;
		}
		else
		{
				$tabs['active'] = false;
		}
	}	
	include TEMPLATES_PATH . "/tab.php";
}

/**
 * Displays the pagetabs
 *
 * @return noreturn
 */
function BuildPageTabs()
{
	AddTab("SourceBans", "http://www.sourcebans.net", "");
	AddTab("SourceMod", "http://www.sourcemod.net", "");
}

/**
 * Rewrites the breadcrumb html
 *
 * @return noreturn
 */
function BuildBreadcrumbs()
{
	$base = $GLOBALS['pagetitle'];
	
	switch($_GET['c'])
	{
		case "admins":
			$cat = "Admin management";
			break;
		case "servers":
			$cat = "Server management";
			break;
		case "bans":
			$cat = "Ban management";
			break;
		case "groups":
			$cat = "Group management";
			break;
		case "settings":
			$cat = "SourceBans settings";
			break;
		case "mods":
			$cat = "Mod management";
			break;
	}
		
	if(!isset($_GET['c']))
	{
		$bread = "<b>" . $base . "</b>";
	}
	else 
	{
		$bread = "<a href='?p=". $_GET['p'] . "'>" . $base . "</a>  &raquo; <b>" . $cat . "</b>"; 
	}
	$text = "&raquo; <a href='?p=home'>Home</a> &raquo; " . $bread;
	echo '<script>$("breadcrumb").setHTML("' . $text . '");</script>';
	
}
/**
 * Creates an anchor tag, and adds tooltip code if needed
 *
 * @param string $title The title of the tooltip/text to link
 * @param string $url The link
 * @param string $tooltip The tooltip message
 * @param string $target The new links target
 * @return noreturn
 */
function CreateLink($title, $url, $tooltip="", $target="_self", $wide=false)
{
	if($wide)
		$class = "perm";
	else 
		$class = "tip";
	if(strlen($tooltip) == 0)
	{
		echo '<a href="' . $url . '" target="' . $target . '">' . $title .' </a>';
	}else{
		echo '<a href="' . $url . '" class="' . $class .'" title="' .  $title . ' :: ' .  $tooltip . '" target="' . $target . '">' . $title .' </a>';
	}
}

/**
 * Creates an anchor tag, and adds tooltip code if needed
 *
 * @param string $title The title of the tooltip/text to link
 * @param string $url The link
 * @param string $tooltip The tooltip message
 * @param string $target The new links target
 * @return URL
 */
function CreateLinkR($title, $url, $tooltip="", $target="_self", $wide=false, $onclick)
{
	if($wide)
		$class = "perm";
	else 
		$class = "tip";
	if(strlen($tooltip) == 0)
	{
		return '<a href="' . $url . '" onclick="' . $onclick . '" target="' . $target . '">' . $title .' </a>';
	}else{
		return '<a href="' . $url . '" class="' . $class .'" title="' .  $title . ' :: ' .  $tooltip . '" target="' . $target . '">' . $title .' </a>';
	}
}

function HelpIcon($title, $text)
{
	return '<img border="0" align="absbottom" src="images/admin/help.png" class="tip" title="' .  $title . ' :: ' .  $text . '">&nbsp;&nbsp;';
}

/**
 * Allows the title of the page to change wherever the code is being executed from
 *
 * @param string $title The new title
 * @return noreturn
 */
function RewritePageTitle($title)
{
	$GLOBALS['TitleRewrite'] = $title;
}

/**
 * Build sub-menu
 *
 * @param array $el The array of elements for the menu
 * @return noreturn
 */
function SubMenu($el)
{
	$output = "";
	foreach($el AS $e)
	{
		$output .= "<a class=\"nav_link\" href=\"" . $e['url'] . "\">" . $e['title']. "</a>";
	}
	$GLOBALS['NavRewrite'] = $output;
}

/**
 * Converts a flag bitmask into a string
 *
 * @param integer $mask The mask to convert
 * @return string
 */
function BitToString($mask, $masktype=0, $head=true)
{
	$string = "";
		if($head)
			$string .= "<span style='font-size:10px;color:#1b75d1;'>Web Permissions</span><br>";
		if($mask == 0)
		{
			$string .= "<i>None</i>";
			return $string;
		}
		if(($mask & ADMIN_LIST_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; View admins<br />";
		if(($mask & ADMIN_ADD_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Add admins<br />";
		if(($mask & ADMIN_EDIT_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Edit admins<br />";
		if(($mask & ADMIN_DELETE_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Delete admins<br />";
			
		if(($mask & ADMIN_LIST_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; View servers<br />";
		if(($mask & ADMIN_ADD_SERVER) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Add servers<br />";
		if(($mask & ADMIN_EDIT_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Edit servers<br />";
		if(($mask & ADMIN_DELETE_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Delete servers<br />";
		
		if(($mask & ADMIN_ADD_BAN) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; Add bans<br />";
		if(($mask & ADMIN_BAN_LIST) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; View bans<br />";
		if(($mask & ADMIN_EDIT_OWN_BANS) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .="&bull; Edit own bans<br />";
		if(($mask & ADMIN_EDIT_GROUP_BANS) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; Edit groups bans<br />";
		if(($mask & ADMIN_EDIT_ALL_BANS) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; Edit all bans<br />";
		if(($mask & ADMIN_BAN_PROTESTS) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; Ban protests<br />";
		if(($mask & ADMIN_BAN_SUBMISSIONS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Ban submissions<br />";	
		
		if(($mask & ADMIN_LIST_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; List groups<br />";
		if(($mask & ADMIN_ADD_GROUP) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; Add groups<br />";
		if(($mask & ADMIN_EDIT_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0)	
			$string .= "&bull; Edit groups<br />";
		if(($mask & ADMIN_DELETE_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Delete groups<br />";
			
		if(($mask & ADMIN_WEB_SETTINGS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Web settings<br />";
			
		if(($mask & ADMIN_LIST_MODS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; List mods<br />";
		if(($mask & ADMIN_ADD_MODS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Add mods<br />";
		if(($mask & ADMIN_EDIT_MODS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Edit mods<br />";
		if(($mask & ADMIN_DELETE_MODS) !=0 || ($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Delete mods<br />";
			
		if(($mask & ADMIN_OWNER) !=0)
			$string .= "&bull; Owner<br />";

	return $string;
}


function SmFlagsToSb($flagstring, $head=true)
{
	
	$string = "";
	if($head)
		$string .= "<span style='font-size:10px;color:#1b75d1;'>Server Permissions</span><br>";
	if(empty($flagstring))
		{
			$string .= "<i>None</i>";
			return $string;
		}
	if((strstr($flagstring, "a") || strstr($flagstring, "z")))
		$string .= "&bull; Reserved slot<br />";
	if((strstr($flagstring, "b") || strstr($flagstring, "z")))
		$string .= "&bull; Generic admin<br />";
	if((strstr($flagstring, "c") || strstr($flagstring, "z")))
		$string .= "&bull; Kick<br />";
	if((strstr($flagstring, "d") || strstr($flagstring, "z")))
		$string .= "&bull; Ban<br />";
	if((strstr($flagstring, "e") || strstr($flagstring, "z")))
		$string .= "&bull; Un-ban<br />";
	if((strstr($flagstring, "f") || strstr($flagstring, "z")))
		$string .= "&bull; Slay<br />";
	if((strstr($flagstring, "g") || strstr($flagstring, "z")))
		$string .= "&bull; Map change<br />";
	if((strstr($flagstring, "h") || strstr($flagstring, "z")))
		$string .= "&bull; Change cvars<br />";
	if((strstr($flagstring, "i") || strstr($flagstring, "z")))
		$string .= "&bull; Run configs<br />";
	if((strstr($flagstring, "j") || strstr($flagstring, "z")))
		$string .= "&bull; Admin chat<br />";
	if((strstr($flagstring, "k") || strstr($flagstring, "z")))
		$string .="&bull; Start votes<br />";
	if((strstr($flagstring, "l") || strstr($flagstring, "z")))
		$string .="&bull; Password server<br />";
	if((strstr($flagstring, "m") || strstr($flagstring, "z")))
		$string .="&bull; RCON<br />";
	if((strstr($flagstring, "n") || strstr($flagstring, "z")))
		$string .="&bull; Enable Cheats<br />";
	if((strstr($flagstring, "z")))
		$string .="&bull; Full Admin<br />";
		
	if((strstr($flagstring, "o") || strstr($flagstring, "z")))
		$string .="&bull; Custom flag 1<br />";
	if((strstr($flagstring, "p") || strstr($flagstring, "z")))
		$string .="&bull; Custom flag 2<br />";
	if((strstr($flagstring, "q") || strstr($flagstring, "z")))
		$string .="&bull; Custom flag 3<br />";
	if((strstr($flagstring, "r") || strstr($flagstring, "z")))
		$string .="&bull; Custom flag 4<br />";
	if((strstr($flagstring, "s") || strstr($flagstring, "z")))
		$string .="&bull; Custom flag 5<br />";
	if((strstr($flagstring, "t") || strstr($flagstring, "z")))
		$string .="&bull; Custom flag 6<br />";

	
	//if(($mask & SM_DEF_IMMUNITY) != 0)
	//{
	//	$flagstring .="&bull; Default immunity<br />";
	//}
	//if(($mask & SM_GLOBAL_IMMUNITY) != 0)
	//{
	//	$flagstring .="&bull; Global immunity<br />";
	//}
	return $string;	
	
}

function PrintArray($array)
{
	echo "<pre>";
		print_r($array);
	echo "</pre>";
}

function NextGid()
{
	$gid = $GLOBALS['db']->GetRow("SELECT MAX(gid) AS next_gid FROM `" . DB_PREFIX . "_groups`");
	return ($gid['next_gid']+1);
}
function NextSGid()
{
	$gid = $GLOBALS['db']->GetRow("SELECT MAX(id) AS next_id FROM `" . DB_PREFIX . "_srvgroups`");
	return ($gid['next_id']+1);
}
function NextSid()
{
	$sid = $GLOBALS['db']->GetRow("SELECT MAX(sid) AS next_sid FROM `" . DB_PREFIX . "_servers`");
	return ($sid['next_sid']+1);
}
function NextAid()
{
	$aid = $GLOBALS['db']->GetRow("SELECT MAX(aid) AS next_aid FROM `" . DB_PREFIX . "_admins`");
	return ($aid['next_aid']+1);
}

function trunc($text, $len, $byword=true) 
{
	if(strlen($text) <= $len)
		return $text;
    $text = $text." ";
    $text = substr($text,0,$len);
    if($byword)
    	$text = substr($text,0,strrpos($text,' '));
    $text = $text."...";
    return $text;
}

function CreateRedBox($title, $content)
{
	$text = '<div id="msg-red-debug" style="">
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>' . $title .'</b>
	<br />
	' . $content . '</i>
</div>';
	
	echo $text;
}
function CreateGreenBox($title, $contnet)
{
	$text = '<div id="msg-green-dbg" style="">
	<i><img src="./images/yay.png" alt="Yay!" /></i>
	<b>' . $title .'</b>
	<br />
	' . $contnet . '</i>
</div>';
	
	echo $text;
}

function CreateQuote()
{
	$quote = array(
		array("Buy a new PC!", "Viper"),
		array("I'm not lazy! I just utilize technical resources!", "Brizad"),
		array("I need to mow the lawn", "sslice"),
		array("Like A Glove!", "Viper"),
		array("Your a Noob and You Know It!", "Viper"),
		array("Get your ass ingame", "Viper"),
		array("Mother F***ing Peices of Sh**", "Viper"),
		array("Shut up Bam", "[Everyone]"),
		array("Hi OllyBunch", "Viper"),
		array("Procrastination is like masturbation. Sure it feels good, but in the end you're only F***ing yourself!", "[Unknown]"),
		array("Rave's momma so fat she sat on the beach and Greenpeace threw her in", "SteamFriend"),
		array("Im just getting a beer", "Faith"),
		array("To be honest " . (isset($_SESSION['user']['user'])?$_SESSION['user']['user']:'...') . ", I DONT CARE!", "Viper"),
		array("Yams", "teame06"),
		array("built in cheat 1.6 - my friend told me theres a cheat where u can buy a car door and run around and it makes u invincible....", "gdogg"),
		array("i just join conversation when i see a chance to tell people they might be wrong, then i quickly leave, LIKE A BAT", "BAILOPAN"),
		array("Lets just blame it on FlyingMongoose", "[Everyone]"),
		array("I wish my lawn was emo, so it would cut itself", "SirTiger"),
	);
	$num = rand(0, sizeof($quote)-1);
	return '"' . $quote[$num][0] . '" - <i>' . $quote[$num][1] . '</i>';
}

function CheckAdminAccess($mask)
{
	if(!check_flags( $_SESSION['user']['aid'], $mask ))
	{
		RedirectJS("?p=login&msg=You dont have access. Login with an account with access");
		die();
	}
}

function RedirectJS($url)
{
	echo '<script>window.location = "' . $url .'";</script>';
}

function RemoveCode($text)
{
	return addslashes(htmlspecialchars(strip_tags($text)));
}

function SecondsToString($sec, $textual=true)
{
	$div = array( 2592000, 604800, 86400, 3600, 60, 1 );
	if($textual)
	{
		$desc = array ('mo','wk','d','hr','min','sec');
		$ret = "";
		for ($i=0;$i<count($div);$i++)
		{
			if (($cou = round($sec / $div[$i])) >= 1)
			{
				$ret .= $cou.' '.$desc[$i].', ';
				$sec %= $div[$i];
			}
		}
		$ret = substr($ret,0,strlen($ret)-2);
	}else{
		$hours = floor ($sec / 60 / 60);
		$sec -= $hours * 60*60;
		$mins = floor ($sec / 60);
		$secs = $sec % 60;
		$ret = $hours . ":" . $mins . ":" . $secs;
	}
	return $ret;
}

function CreateHostnameCache()
{
	require_once INCLUDES_PATH.'/CServerInfo.php';
	
	$res = $GLOBALS['db']->Execute("SELECT sid, ip, port, modid, rcon FROM ".DB_PREFIX."_servers WHERE sid > 0 ORDER BY sid");
	$servers = array();
	while (!$res->EOF)
	{
		$info = array();
		$sinfo = new CServerInfo($res->fields[1],$res->fields[2]);
		$info = $sinfo->getInfo();
		$servers[$res->fields[0]] = $info['hostname'];
		$res->MoveNext();
	}
	return($servers);
}

function FetchIp($ip)
{
	global $addr;
	$addr = $ip;
	return include INCLUDES_PATH.'/read_country.php';
}

function PageDie()
{
	include TEMPLATES_PATH.'/footer.php';
	die();
}

function GetMapImage($map)
{
	if(@file_exists(SB_MAP_LOCATION . "/" . $map . ".jpg"))
		return "images/maps/" . $map . ".jpg";
	else 
		return "images/maps/nomap.jpg";
}

?>
