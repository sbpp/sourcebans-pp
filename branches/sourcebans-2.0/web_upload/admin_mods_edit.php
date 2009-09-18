<?php
require_once 'init.php';
require_once READERS_DIR . 'mods.php';
require_once WRITERS_DIR . 'mods.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['edit_mod']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'EDIT_MODS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      ModsWriter::edit($_POST['id'], $_POST['name'], $_POST['folder'], $_POST['icon'], isset($_POST['enabled']));
      
      exit(json_encode(array(
        'redirect' => 'admin_mods.php'
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $mods_reader = new ModsReader();
  $mods        = $mods_reader->executeCached(ONE_DAY);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($mods[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $mod         = $mods[$_GET['id']];
  
  $page->assign('mod_name',    $mod['name']);
  $page->assign('mod_folder',  $mod['folder']);
  $page->assign('mod_icon',    $mod['icon']);
  $page->assign('mod_enabled', $mod['enabled']);
  $page->display('page_admin_mods_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>