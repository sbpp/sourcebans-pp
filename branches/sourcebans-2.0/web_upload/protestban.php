<?php
require_once 'init.php';
require_once WRITERS_DIR . 'protests.php';

$config  = Env::get('config');
$phrases = Env::get('phrases');
$page    = new Page(ucwords($phrases['protest_ban']));

try
{
  if($config['config.enablesubmit'] != 1)
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      ProtestsWriter::add($_POST['name'], $_POST['type'], $_POST['steam'], $_POST['ip'], $_POST['reason'], $_POST['email']);
      exit;
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $page->display('page_protestban');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>