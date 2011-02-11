<?php
require_once 'api.php';

/*
@todo somehow allow default page selection again
$config = SBConfig::getEnv('config');
$config['config.defaultpage']
*/

$pageid = SBConfig::getVar('p', 'dashboard');

if (file_exists(sprintf("%s/%s.php", PAGES_DIR, $pageid)))
{
  // @todo Once a static loader method is made in SBConfig or Page class
}


//SBConfig::setEnv('active', $active);
//require_once $active;
