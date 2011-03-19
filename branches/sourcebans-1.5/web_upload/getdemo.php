<?php
/**
 * Fetch file demo file by demoid
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */

include_once("init.php");

$GLOBALS['db']->Execute("SET NAMES utf8"); 
$st = $GLOBALS['db']->Prepare("SELECT filename, origname FROM " . DB_PREFIX . "_demos WHERE demtype=? and demid=?");
$res = $GLOBALS['db']->Execute($st,array($_GET['type'],$_GET['id']));
header('Content-type: application/force-download');
header('Content-Transfer-Encoding: Binary');
header('Content-disposition: attachment; filename="'.$res->fields[1].'"');
@readfile(SB_DEMOS . "/" . $res->fields[0]);
?>