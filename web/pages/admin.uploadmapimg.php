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

// See admin.kickit.php for why this chdir() is needed.
chdir(ROOT);

if (!$userbank->HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::AddServer))) {
    Log::add(LogType::Warning, "Hacking Attempt", $userbank->GetProperty('user')." tried to upload a mapimage, but doesn't have access.");
    die("You don't have access to this!");
}

$message = "";
if (isset($_POST['upload'])) {
    CSRF::rejectIfInvalid();
    if (checkExtension($_FILES['mapimg_file']['name'], ['jpg'])) {
        move_uploaded_file($_FILES['mapimg_file']['tmp_name'], SB_MAPS . "/" . $_FILES['mapimg_file']['name']);
        // Issue #1113: filename is admin-controlled; see admin.uploaddemo.php
        // for the rationale behind json_encode + HEX flags.
        $jsFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;
        $jsName = json_encode((string) $_FILES['mapimg_file']['name'], $jsFlags);
        $message = "<script>window.opener.mapimg($jsName);self.close()</script>";
        Log::add(LogType::Message, "Map Image Uploaded", "A new map image has been uploaded: $_FILES[mapimg_file][name]");
    } else {
        $message = "<b> File must be jpg filetype.</b><br><br>";
    }
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\UploadFileView(
    title: 'Upload Mapimage',
    message: $message,
    input_name: 'mapimg_file',
    form_name: 'mapimgup',
    formats: 'a JPG',
));
