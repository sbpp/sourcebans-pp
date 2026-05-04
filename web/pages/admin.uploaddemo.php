<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

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

if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_ADD_BAN | ADMIN_EDIT_OWN_BANS | ADMIN_EDIT_GROUP_BANS | ADMIN_EDIT_ALL_BANS)) {
    Log::add("w", "Hacking Attempt", $userbank->GetProperty('user')." tried to upload a demo, but doesn't have access.");
    die("You don't have access to this!");
}

$message = "";

if (isset($_POST['upload'])) {
    CSRF::rejectIfInvalid();
    if (checkExtension($_FILES['demo_file']['name'], ['zip', 'rar', 'dem', '7z', 'bz2', 'gz'])) {
        $filename = md5(time() . rand(0, 1000));
        move_uploaded_file($_FILES['demo_file']['tmp_name'], SB_DEMOS . "/" . $filename);
        // Issue #1113: original filename is admin-controlled and used to be
        // interpolated raw into a <script>…</script> blob, so anything an
        // uploader put in the filename ran in their (or the parent window's)
        // browser. JSON-encode with HEX flags so `'`, `"`, `<`, `>`, `&` all
        // become \uXXXX escapes that survive both the HTML-attribute and the
        // JS-string layers the popup template renders us into.
        $jsFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;
        $jsHash = json_encode($filename, $jsFlags);
        $jsName = json_encode((string) $_FILES['demo_file']['name'], $jsFlags);
        $message = "<script>window.opener.demo($jsHash,$jsName);self.close()</script>";
        Log::add("m", "Demo Uploaded", "A new demo has been uploaded: $_FILES[demo_file][name]");
    } else {
        $message = "<b> File must be dem, zip, rar, 7z, bz2 or gz filetype.</b><br><br>";
    }
}

$theme->assign("title", "Upload Demo");
$theme->assign("message", $message);
$theme->assign("input_name", "demo_file");
$theme->assign("form_name", "demup");
$theme->assign("formats", "a DEM, ZIP, RAR, 7Z, BZ2 or GZ");

$theme->display('page_uploadfile.tpl');
