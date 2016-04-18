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

include_once("../init.php");
include_once("../includes/system-functions.php");
global $theme, $userbank;

if (!$userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_SERVER))
{
    $log = new CSystemLog("w", "Hacking Attempt", $userbank->GetProperty('user') . " tried to upload a mapimage, but doesn't have access.");
	echo 'You don\'t have access to this!';
	die();
}

$message = "";
if(isset($_POST['upload']))
{
	if(CheckExt($_FILES['mapimg_file']['name'], "jpg"))
	{
		move_uploaded_file($_FILES['mapimg_file']['tmp_name'],SB_MAP_LOCATION."/".$_FILES['mapimg_file']['name']);
		$message =  "<script>window.opener.mapimg('" . $_FILES['mapimg_file']['name'] . "');self.close()</script>";
		$log = new CSystemLog("m", "Map Image Uploaded", "A new map image has been uploaded: ".htmlspecialchars($_FILES['mapimg_file']['name']));
	}
	else 
	{
		$message =  "<b> File must be jpg filetype.</b><br><br>";
	}
}

$theme->assign("title", "Upload Mapimage");
$theme->assign("message", $message);
$theme->assign("input_name", "mapimg_file");
$theme->assign("form_name", "mapimgup");
$theme->assign("formats", "a JPG");

$theme->display('page_uploadfile.tpl');
?>
