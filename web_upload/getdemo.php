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

include_once("init.php");

$GLOBALS['db']->Execute("SET NAMES utf8"); 
$st = $GLOBALS['db']->Prepare("SELECT `filename`, `origname` FROM ".DB_PREFIX."_demos WHERE demtype=? and demid=?");
$res = $GLOBALS['db']->Execute($st,array($_GET['type'],$_GET['id']));
header('Content-type: application/force-download');
header('Content-Transfer-Encoding: Binary');
header('Content-disposition: attachment; filename='.$res->fields[1]);
@readfile(SB_DEMOS . "/" . $res->fields[0]);
?>