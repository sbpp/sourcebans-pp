<?php
require_once 'api.php';

$config  = SBConfig::getEnv('config');
$phrases = SBConfig::getEnv('phrases');
$page    = new Page($phrases['servers'], !isset($_GET['nofullpage']));

try
{
  $servers = SB_API::getServers();
  
  $order   = isset($_GET['order']) && is_string($_GET['order']) ? $_GET['order'] : 'asc';
  $sort    = isset($_GET['sort'])  && is_string($_GET['sort'])  ? $_GET['sort']  : 'mod_name';
  
  Util::array_qsort($servers, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC);
  
  foreach($servers as &$server)
    Util::array_qsort($server['players'], 'score', SORT_DESC);
  
  $page->assign('servers', $servers);
  $page->assign('order',   $order);
  $page->assign('sort',    $sort);
  $page->display('page_servers');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>