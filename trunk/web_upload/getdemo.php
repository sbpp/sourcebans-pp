<?php
/**
 * =============================================================================
 * Fetch file demo file by demoid
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: getdemo.php 24 2007-11-06 18:17:05Z olly $
 * =============================================================================
 */

require_once("init.php");

if(!isset($_GET['id']) || !isset($_GET['type']))
  die('No id or type parameter.');

if(strcasecmp($_GET['type'], "B") != 0 && strcasecmp($_GET['type'], "S") != 0)
  die('Bad type');

$id = (int)$_GET['id'];

$demo = $GLOBALS['db']->GetRow("SELECT filename, origname FROM `".DB_PREFIX."_demos` WHERE demtype=? AND demid=?;", array($_GET['type'], $id));

if(!$demo)
{
  die('Demo not found.');
}

if(!file_exists(SB_DEMOS . "/" . $demo['filename']))
{
  die('File not found.');
}

header('Content-type: application/force-download');
header('Content-Transfer-Encoding: Binary');
header('Content-disposition: attachment; filename="' . $demo['origname'] . '"');
header("Content-Length: " . filesize(SB_DEMOS . "/" . $demo['filename']));
readfile(SB_DEMOS . "/" . $demo['filename']);
?>