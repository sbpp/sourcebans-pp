<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2019 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

include_once("../init.php");
include_once("../includes/system-functions.php");
global $theme, $userbank;

if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_EDIT_MODS | ADMIN_ADD_MODS)) {
    Log::add("w", "Hacking Attempt", $userbank->GetProperty('user')." tried to upload a mod icon, but doesn't have access.");
    die("You don't have access to this!");
}

$message = "";
if (isset($_POST['upload'])) {
    if (checkExtension($_FILES['icon_file']['name'], ['gif', 'jpg', 'png'])) {
        move_uploaded_file($_FILES['icon_file']['tmp_name'], SB_ICONS . "/" . $_FILES['icon_file']['name']);
        $message = "<script>window.opener.icon('" . $_FILES['icon_file']['name'] . "');self.close()</script>";
        Log::add("m", "Mod Icon Uploaded", "A new mod icon has been uploaded: $_FILES[icon_file][name]");
    } else {
        $message = "<b> File must be gif, jpg or png filetype.</b><br><br>";
    }
}

$theme->assign("title", "Upload Icon");
$theme->assign("message", $message);
$theme->assign("input_name", "icon_file");
$theme->assign("form_name", "iconup");
$theme->assign("formats", "a GIF, PNG or JPG");

$theme->display('page_uploadfile.tpl');
