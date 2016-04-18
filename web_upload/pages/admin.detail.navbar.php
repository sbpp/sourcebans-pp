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
