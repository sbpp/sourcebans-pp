<?php
/**
 * Update the database structure from RC1d -> RC2
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
define('IN_SB', true);
define('ROOT', dirname(__FILE__) . '/../');
define('INCLUDES_PATH', ROOT . 'includes/');

require_once ROOT . '../config.php';
require_once INCLUDES_PATH . 'adodb/adodb.inc.php';

echo '- Starting <b>SourceBans</b> database update from RC1d to RC2 -<br />';
$db = ADONewConnection('mysql://' . DB_USER . ':' . DB_PASS . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);

$db->Execute('ALTER TABLE ' . DB_PREFIX . '_admins
              ADD         lastvisit DATETIME NULL');

echo 'Done updating. Please delete this file.<br />';