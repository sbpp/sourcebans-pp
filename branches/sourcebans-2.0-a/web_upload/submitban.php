<?php
require_once 'api.php';

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
      $id = SB_API::addSubmission(strtoupper($_POST['steam']), $_POST['ip'], $_POST['name'], $_POST['reason'], $_POST['subname'], $_POST['subemail'], $_POST['server']);
      
      // If one or more demos were uploaded, add them
      foreach($_FILES['demo'] as $demo)
        SB_API::addDemo($id, SUBMISSION_TYPE, $demo['name'], $demo['tmp_name']);
      
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
  
  $page->assign('servers', SB_API::getServers());
  $page->display('page_submitban');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>