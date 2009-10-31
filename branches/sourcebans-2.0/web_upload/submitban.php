<?php
require_once 'init.php';
require_once READERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'submissions.php';
require_once WRITERS_DIR . 'demos.php';

$config  = Env::get('config');
$phrases = Env::get('phrases');
$page    = new Page(ucwords($phrases['submit_ban']), !isset($_GET['nofullpage']));

try
{
  if(!$config['config.enablesubmit'])
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      $id = SubmissionsWriter::add(strtoupper($_POST['steam']), $_POST['ip'], $_POST['name'], $_POST['reason'], $_POST['subname'], $_POST['subemail'], $_POST['server']);
      
      // If one or more demos were uploaded, add them
      foreach($_FILES['demo'] as $demo)
        DemosWriter::add($id, SUBMISSION_TYPE, $demo['name'], $demo['tmp_name']);
      
      exit(json_encode(array(
        'redirect' => Util::buildQuery()
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $servers_reader = new ServersReader();
  
  $servers        = $servers_reader->executeCached(ONE_MINUTE);
  
  $page->assign('servers', $servers);
  $page->display('page_submitban');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>