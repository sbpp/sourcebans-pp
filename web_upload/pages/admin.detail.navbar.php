<?php 
/**
 * =============================================================================
 * Build our navigation bar
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.detail.navbar.php 24 2007-11-06 18:17:05Z olly $
 * =============================================================================
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 
global $theme;

$first = true; 
$i=0;
$tabs = array();
foreach($var AS $v)
{ 
	if(empty($v['title']))
	{
		$i++; continue;
	} 
	if($first) 
		$GLOBALS['enable'] = $v['id']; 
	if(isset($v['external']) && $v['external'] == true) 
	{
		$lnk = $v['url']; 
		$click = "";
	} 
	else 
	{
		$lnk = "#^" . $v['id']; 
		$click = "SwapPane(". $v['id'] .");";
	} 
	if($i == 0) 
		$class = "active"; 
	else 
		$class = "";
	$itm = array();
	$itm['tab'] = "<li id='tab-". $v['id'] . "' class='" . $class . "'><a href='$lnk' id='admin_tab_".$v['id']."' onclick=\"$click\"> " . $v['title'] . "</a></li>";
	array_push($tabs, $itm) ;
	$i++;
	$first=false;
}

if($_GET['p'] == "account")
	$theme->assign('pane_image','<img src="themes/' . SB_THEME . '/images/admin/your_account.png"> </div>') ;
else 
	$theme->assign('pane_image', '<img src="themes/' . SB_THEME . '/images/admin/'.  $_GET['c'] . '.png"> </div>');
	
$theme->assign('tabs', $tabs);

$theme->display('item_admin_tabs.tpl');
?>
