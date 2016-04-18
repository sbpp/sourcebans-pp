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

require_once("init.php");

if(!isset($_GET['id']) || !isset($_GET['type']))
  die('No id or type parameter.');

if(strcasecmp($_GET['type'], "B") != 0 && strcasecmp($_GET['type'], "S") != 0)
  die('Bad type');

$id = (int)$_GET['id'];

$demo = $GLOBALS['db']->GetRow("SELECT filename, origname FROM `".DB_PREFIX."_demos` WHERE demtype=? AND demid=?;", array($_GET['type'], $id));
//Official Fix: https://code.google.com/p/sourcebans/source/detail?r=165
if(!$demo)
{
  die('Demo not found.');
}

$demo['filename'] = basename($demo['filename']);

if(!in_array($demo['filename'], scandir(SB_DEMOS)) || !file_exists(SB_DEMOS . "/" . $demo['filename']))
{
  die('File not found.');
}

header('Content-type: application/force-download');
header('Content-Transfer-Encoding: Binary');
header('Content-disposition: attachment; filename="' . $demo['origname'] . '"');
header("Content-Length: " . filesize(SB_DEMOS . "/" . $demo['filename']));
readfile(SB_DEMOS . "/" . $demo['filename']);
?>