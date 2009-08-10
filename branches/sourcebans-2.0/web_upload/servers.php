<?php
require_once 'init.php';
require_once READERS_DIR . 'servers.php';

$config  = Env::get('config');
$phrases = Env::get('phrases');
$page    = new Page(ucwords($phrases['servers']));

try
{
  $servers_reader = new ServersReader();
  
  if(isset($_GET['sort']) && is_string($_GET['sort']))
    $servers_reader->sort = $_GET['sort'];
  
  $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
  
  $page->assign('servers', $servers);
  $page->display('page_servers');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>