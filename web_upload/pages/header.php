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

global $userbank, $theme, $xajax,$user,$start;
$time = microtime();
$time = explode(" ", $time);
$time = $time[1] + $time[0];
$start = $time;
ob_start(); 

if(!defined("IN_SB"))
{
	echo "You should not be here. Only follow links!";
	die();
}

if(isset($_GET['c']) && $_GET['c']  == "settings")
{
	$theme->assign('tiny_mce_js', '<script type="text/javascript" src="./includes/tinymce/tiny_mce.js"></script>
					<script language="javascript" type="text/javascript">
					tinyMCE.init({
						mode : "textareas",
						theme : "advanced",
						plugins : "inlinepopups,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,insertdatetime,preview,zoom,media,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras",
						theme_advanced_buttons1 : "bold,italic,underline,|,justifyleft,justifycenter,justifyright,justifyfull,|,fontselect,fontsizeselect",
						theme_advanced_buttons2 : "cut,copy,paste,|,search,replace,|,bullist,numlist,|,outdent,indent,|,undo,redo,|,link,unlink,anchor,image,help,code,|,forecolor,backcolor",
						theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,charmap,emotions,iespell,media",
						theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,|,visualchars,nonbreaking",
						theme_advanced_toolbar_location : "top",
						theme_advanced_toolbar_align : "left",
						theme_advanced_path_location : "bottom",
						extended_valid_elements : "a[name|href|target|title|onclick],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]"
					});
					</script>');
} else
	$theme->assign('tiny_mce_js', '');

$theme->assign('xajax_functions',  $xajax->printJavascript("scripts", "xajax.js"));
$theme->assign('header_title', $GLOBALS['config']['template.title']);
$theme->assign('header_logo', $GLOBALS['config']['template.logo']);
$theme->assign('username', $userbank->GetProperty("user"));
$theme->assign('logged_in', $userbank->is_logged_in());
$theme->assign('theme_name', isset($GLOBALS['config']['config.theme'])?$GLOBALS['config']['config.theme']:'default');
$theme->display('page_header.tpl');
?>        
