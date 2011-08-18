<?php
/**
 * Fetch demo file by demo id
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
require_once 'init.php';

if(!isset($_GET['type'], $_GET['id']))
{
  echo "You have to specify the demo type and id. Only follow links!";
}

$demo = $GLOBALS['db']->GetOne('SELECT filename, origname
                                FROM   ' . DB_PREFIX . '_demos
                                WHERE  demtype = ?
                                  AND  demid   = ?',
                               array($_GET['type'], $_GET['id']));

header('Content-type: application/force-download');
header('Content-Transfer-Encoding: Binary');
header('Content-disposition: attachment; filename="' . $demo['origname'] . '"');
@readfile(SB_DEMOS . '/' . $demo['filename']);