<?php
require_once 'init.php';
require_once READERS_DIR . 'servers.php';

$config  = Env::get('config');
$phrases = Env::get('phrases');
$page    = new Page(ucwords($phrases['servers']));

try
{
  $servers_reader = new ServersReader();
  
  $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
  
  if(isset($_GET['sort'])  && is_string($_GET['sort']))
    $sort  = $_GET['sort'];
  else
    $sort  = 'mod_name';
  
  if(isset($_GET['order']) && is_string($_GET['order']))
    $order = $_GET['order'];
  else
    $order = SORT_ASC;
  
  Util::array_qsort($servers, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC);
  
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