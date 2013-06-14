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
global $theme, $userbank;

if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_ALL_BANS))
{
    $log = new CSystemLog("w", "Hacking Attempt", $userbank->GetProperty('user') . " tried to upload a demo, but doesn't have access.");
	echo 'You don\'t have access to this!';
	die();
}

$message = "";

if(isset($_POST['upload']))
{
	if(CheckExt($_FILES['demo_file']['name'], "zip") || CheckExt($_FILES['demo_file']['name'], "rar") || CheckExt($_FILES['demo_file']['name'], "dem") ||
	   CheckExt($_FILES['demo_file']['name'], "7z") || CheckExt($_FILES['demo_file']['name'], "bz2") || CheckExt($_FILES['demo_file']['name'], "gz"))
	{
		$filename = md5(time().rand(0, 1000));
		move_uploaded_file($_FILES['demo_file']['tmp_name'],SB_DEMOS."/".$filename);
		$message =  "<script>window.opener.demo('" . $filename . "','" . $_FILES['demo_file']['name'] . "');self.close()</script>";
        $log = new CSystemLog("m", "Demo Uploaded", "A new demo has been uploaded: ".htmlspecialchars($_FILES['demo_file']['name']));
	}
	else 
	{
		$message =  "<b> File must be dem, zip, rar, 7z, bz2 or gz filetype.</b><br><br>";
	}
}

$theme->assign("title", "Upload Demo");
$theme->assign("message", $message);
$theme->assign("input_name", "demo_file");
$theme->assign("form_name", "demup");
$theme->assign("formats", "a DEM, ZIP, RAR, 7Z, BZ2 or GZ");

$theme->display('page_uploadfile.tpl');
?>

