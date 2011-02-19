<?php 
/**
 * Installer
 * 
 * @author     InterWave Studios
 * @copyright  SourceBans (C)2007-2011 InterWaveStudios.com.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Installer
 * @version    $Id$
 */
include_once 'init.php';
include_once INCLUDES_PATH . 'system-functions.php';

$steps      = array(
  1 => 'License Agreement',
  2 => 'Database Details',
  3 => 'System Requirements',
  4 => 'Table Creation',
  5 => 'Initial Setup',
  6 => 'AMXBans Import',
);
$step       = (isset($_GET['step']) && isset($steps[$_GET['step']]) ? $_GET['step'] : 1);
$page_title = $steps[$step];

include TEMPLATE_PATH . 'header.php';
include TEMPLATE_PATH . 'page.' . $step . '.php';
include TEMPLATE_PATH . 'footer.php';