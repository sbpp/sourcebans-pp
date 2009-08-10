<?php
/**
 * =============================================================================
 * SourceBans Updater
 * 
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id$
 * =============================================================================
 */

include '../init.php';
include CLASS_DIR . 'updater.class.php';

$page = new Page('Updater', false);
$page->assign('current_version', SBUpdater::getCurrentVersion());
$page->assign('latest_version',  SBUpdater::getLatestVersion());

if(SBUpdater::needsUpdate())
{
  Util::clearCache();
  
  $page->assign('needs_update', true);
  $page->assign('updates',      SBUpdater::doUpdates());
}

$page->display('page_updater');
?>