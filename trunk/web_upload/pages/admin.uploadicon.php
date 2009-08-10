<?php
/**
 * =============================================================================
 * Update an icon
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.uploadicon.php 179 2008-12-11 20:37:10Z peace-maker $
 * =============================================================================
 */

include_once("../init.php");
include_once("../includes/system-functions.php");
global $theme;
$message = "";
if(isset($_POST['upload']))
{
	if(CheckExt($_FILES['demo_file']['name'], "gif") || CheckExt($_FILES['demo_file']['name'], "jpg") || CheckExt($_FILES['demo_file']['name'], "png"))
	{
		move_uploaded_file($_FILES['demo_file']['tmp_name'],SB_ICONS."/".$_FILES['demo_file']['name']);
		$message =  "<script>window.opener.icon('" . $_FILES['demo_file']['name'] . "');self.close()</script>";
	}
	else 
	{
		$message =  "<b> File must be gif, jpg or png filetype.</b><br><br>";
	}
}

$theme->assign("title", "Upload Icon");
$theme->assign("message", $message);
$theme->assign("input_name", "demo_file");
$theme->assign("form_name", "demup");
$theme->assign("formats", "a GIF, PNG or JPG");

$theme->display('page_uploadfile.tpl');
?>
