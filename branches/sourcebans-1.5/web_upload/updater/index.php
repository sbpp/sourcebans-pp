<?php
/**
 * =============================================================================
 * Updater Data
 * 
 * @author SteamFriends Development Team
 * @version 1.2.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id$
 * =============================================================================
 */
 define('IS_UPDATE', true);
 include "../init.php";
//clear compiled themes
$cachedir = dir(SB_THEMES_COMPILE);
while (($entry = $cachedir->read()) !== false) {
	if (is_file($cachedir->path.$entry)) {
		unlink($cachedir->path.$entry);
	}
}
$cachedir->close();
 include INCLUDES_PATH . "/CUpdate.php";
 $updater = new CUpdater();
 
 $setup = "Checking current database version...<b> " . $updater->getCurrentRevision() . "</b>";
 if(!$updater->needsUpdate())
 {
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
?>