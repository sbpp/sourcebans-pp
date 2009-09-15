<?php
require_once 'init.php';
require_once READERS_DIR . 'counts.php';
require_once READERS_DIR . 'mods.php';
require_once WRITERS_DIR . 'mods.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['mods']);

try
{
  if(!$userbank->HasAccess(array('OWNER', 'ADD_MODS', 'DELETE_MODS', 'EDIT_MODS', 'LIST_MODS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      switch($_POST['action'])
      {
        case 'add':
          if(!$userbank->HasAccess(array('OWNER', 'ADD_MODS')))
            throw new Exception($phrases['access_denied']);
          
          ModsWriter::add($_POST['name'], $_POST['folder'], $_POST['icon'], isset($_POST['enabled']));
          break;
        default:
          throw new Exception('Invalid action specified.');
      }
      
      exit(json_encode(array(
        'redirect' => Env::get('active')
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $counts_reader = new CountsReader();
  $mods_reader   = new ModsReader();
  
  $counts        = $counts_reader->executeCached(ONE_MINUTE * 5);
  $mods          = $mods_reader->executeCached(ONE_DAY);
  
  $page->assign('permission_add_mods',    $userbank->HasAccess(array('OWNER', 'ADD_MODS')));
  $page->assign('permission_delete_mods', $userbank->HasAccess(array('OWNER', 'DELETE_MODS')));
  $page->assign('permission_edit_mods',   $userbank->HasAccess(array('OWNER', 'EDIT_MODS')));
  $page->assign('permission_list_mods',   $userbank->HasAccess(array('OWNER', 'LIST_MODS')));
  $page->assign('mods',                   $mods);
  $page->assign('mod_count',              $counts['mods']);
  $page->display('page_admin_mods');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>