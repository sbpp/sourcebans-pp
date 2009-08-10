<?php
/**
 * =============================================================================
 * Upload a demo
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: admin.uploaddemo.php 179 2008-12-11 20:37:10Z peace-maker $
 * =============================================================================
 */


include_once("../init.php");
include_once("../includes/system-functions.php");
global $theme;
$message = "";

if(isset($_POST['upload']))
{
	if(CheckExt($_FILES['demo_file']['name'], "zip") || CheckExt($_FILES['demo_file']['name'], "rar") || CheckExt($_FILES['demo_file']['name'], "dem"))
	{
		$filename = md5(time().rand(0, 1000));
		move_uploaded_file($_FILES['demo_file']['tmp_name'],SB_DEMOS."/".$filename);
		$message =  "<script>window.opener.demo('" . $filename . "','" . $_FILES['demo_file']['name'] . "');self.close()</script>";
	}
	else 
	{
		$message =  "<b> File must be zip, rar or dem filetype.</b><br><br>";
	}
}

$theme->assign("title", "Upload Demo");
$theme->assign("message", $message);
$theme->assign("input_name", "demo_file");
$theme->assign("form_name", "demup");
$theme->assign("formats", "a ZIP, RAR, or DEM");

$theme->display('page_uploadfile.tpl');
?>

