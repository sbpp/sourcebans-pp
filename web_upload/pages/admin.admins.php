<?php
/**
 * =============================================================================
 * Our admins page
 *
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 *
 * @version $Id: admin.admins.php 270 2009-06-22 22:01:44Z peace-maker $
 * =============================================================================
 */
?>

<div id="admin-page-content">
<?php
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
global $userbank, $ui;

if (isset($_GET['page']) && $_GET['page'] > 0)
{
	$page = intval($_GET['page']);
}

$AdminsStart = intval(($page-1) * $AdminsPerPage);
$AdminsEnd = intval($AdminsStart+$AdminsPerPage);
if ($AdminsEnd > $admin_count) $AdminsEnd = $admin_count;

// List Page
$admin_list = array();
foreach($admins AS $admin)
{
	$admin['immunity'] = $userbank->GetProperty("srv_immunity", $admin['aid']);
	$admin['web_group'] = $userbank->GetProperty("group_name", $admin['aid']);
	$admin['server_group'] = $userbank->GetProperty("srv_groups", $admin['aid']);
	if(empty($admin['web_group']) || $admin['web_group']==" ")
	{
  		$admin['web_group'] = "No Group/Individual Permissions";
	}
	if(empty($admin['server_group']) || $admin['server_group']==" ")
	{
		$admin['server_group'] = "No Group/Individual Permissions";
	}
	$num = $GLOBALS['db']->GetRow("SELECT count(authid) AS num FROM `" . DB_PREFIX . "_bans` WHERE aid = '".$admin['aid']."'");
	$admin['bancount'] = $num['num'];

	$nodem = $GLOBALS['db']->GetRow("SELECT count(B.bid) AS num FROM `" . DB_PREFIX . "_bans` AS B WHERE aid = '".$admin['aid']."' AND NOT EXISTS (SELECT D.demid FROM `" . DB_PREFIX . "_demos` AS D WHERE D.demid = B.bid)");
	$admin['aid'] = $admin['aid'];
	$admin['nodemocount'] = $nodem['num'];

  	$admin['name'] = stripslashes($admin['user']);
  	$admin['server_flag_string'] = SmFlagsToSb($userbank->GetProperty("srv_flags",$admin['aid']));
  	$admin['web_flag_string'] = BitToString($userbank->GetProperty("extraflags",$admin['aid']));
  	$admin['lastvisit'] = SBDate($dateformat,$userbank->GetProperty("lastvisit", $admin['aid']));
  	array_push($admin_list, $admin);
}

if ($page > 1)
{
	$prev = CreateLinkR('<img border="0" alt="prev" src="images/left.gif" style="vertical-align:middle;" /> prev',"index.php?p=admin&c=admins&page=" .($page-1). $advSearchString);
}
else
{
	$prev = "";
}
if ($AdminsEnd < $admin_count)
{
	$next = CreateLinkR('next <img border="0" alt="prev" src="images/right.gif" style="vertical-align:middle;" />',"index.php?p=admin&c=admins&page=" .($page+1).$advSearchString);
}
else
	$next = "";

//=================[ Start Layout ]==================================
$admin_nav = 'displaying&nbsp;'.$AdminsStart.'&nbsp;-&nbsp;'.$AdminsEnd.'&nbsp;of&nbsp;'.$admin_count.'&nbsp;results';

if (strlen($prev) > 0)
{
	$admin_nav .= ' | <b>'.$prev.'</b>';
}
if (strlen($next) > 0)
{
	$admin_nav .= ' | <b>'.$next.'</b>';
}

$pages = ceil($admin_count/$AdminsPerPage);
if($pages > 1) {
	$admin_nav .= '&nbsp;<select onchange="changePage(this,\'A\',\''.$_GET['advSearch'].'\',\''.$_GET['advType'].'\');">';
	for($i=1;$i<=$pages;$i++) {
		if($i==$_GET["page"]) {
			$admin_nav .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
			continue;
		}
		$admin_nav .= '<option value="' . $i . '">' . $i . '</option>';
	}
	$admin_nav .= '</select>';
}

echo '<div id="0" style="display:none;">';
	$theme->assign('permission_listadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_ADMINS));
	$theme->assign('permission_editadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_EDIT_ADMINS));
	$theme->assign('permission_deleteadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_DELETE_ADMINS));
	$theme->assign('admin_count', $admin_count);
	$theme->assign('admin_nav', $admin_nav);
	$theme->assign('admins', $admin_list);
	$theme->display('page_admin_admins_list.tpl');
echo '</div>';




// Add Page
$group_list = 				$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type = '3'");
$servers = 					$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_servers`");
$server_admin_group_list = 	$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_srvgroups`");
$server_group_list = 		$GLOBALS['db']->GetAll("SELECT * FROM `" . DB_PREFIX . "_groups` WHERE type != 3");
$server_list = array();
$serverscript = "<script type=\"text/javascript\">";
foreach($servers AS $server)
{
    $serverscript .= "xajax_ServerHostPlayers('".$server['sid']."', 'id', 'sa".$server['sid']."');";
	$info['sid'] = $server['sid'];
	$info['ip'] = $server['ip'];
	$info['port'] = $server['port'];
	array_push($server_list, $info);
}
$serverscript .= "</script>";

echo '<div id="1" style="display:none;">';
	$theme->assign('group_list', $group_list);
	$theme->assign('server_list', $server_list);
	$theme->assign('server_script', $serverscript);
	$theme->assign('server_admin_group_list', $server_admin_group_list);
	$theme->assign('server_group_list', $server_group_list);
	$theme->assign('permission_addadmin', $userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_ADMINS));
	$theme->display('page_admin_admins_add.tpl');
echo '</div>';

?>
</div>
