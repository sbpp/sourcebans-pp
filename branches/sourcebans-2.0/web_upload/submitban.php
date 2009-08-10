<?php
require_once 'init.php';
require_once READERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'submissions.php';

$config  = Env::get('config');
$phrases = Env::get('phrases');
$page    = new Page(ucwords($phrases['submit_ban']));

try
{
  if($config['config.enablesubmit'] != 1)
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    SubmissionsWriter::add($_POST['name'], $_POST['steam'], $_POST['ip'], $_POST['reason'], $_POST['server'], $_POST['subname'], $_POST['subemail']);
  }
  
  $servers_reader = new ServersReader();
  $servers        = $servers_reader->executeCached(ONE_MINUTE * 5);
  
  $page->assign('servers', $servers);
  $page->display('page_submitban');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>