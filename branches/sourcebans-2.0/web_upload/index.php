<?php
require_once 'init.php';

$config = Env::get('config');

switch($config['config.defaultpage'])
{
  case 1:
    $active = 'banlist.php';
    break;
  case 2:
    $active = 'servers.php';
    break;
  case 3:
    $active = 'submitban.php';
    break;
  case 4:
    $active = 'protestban.php';
    break;
  default:
    $active = 'dashboard.php';
}

Env::set('active', $active);
require_once $active;
?>