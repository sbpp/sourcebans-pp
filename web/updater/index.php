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

define('IS_UPDATE', true);
include "../init.php";
//clear compiled themes
$cachedir = dir(SB_THEMES_COMPILE);
while (($entry = $cachedir->read()) !== false) {
    if (is_file($cachedir->path . $entry)) {
        unlink($cachedir->path . $entry);
    }
}
$cachedir->close();
include INCLUDES_PATH . "/CUpdate.php";
$updater = new CUpdater();

$setup = "Checking current database version...<b> " . $updater->getCurrentRevision() . "</b>";
if (!$updater->needsUpdate()) {
    $setup .= "<br />Installation up-to-date.";
    $theme->assign('setup', $setup);
    $theme->assign('progress', "");
    $theme->display('updater.tpl');
    die();
}
$setup .= "<br />Updating database to version: <b>" . $updater->getLatestPackageVersion() . "</b>";

$progress = $updater->doUpdates();

$theme->assign('setup', $setup);
$theme->assign('progress', $progress);
$theme->display('updater.tpl');