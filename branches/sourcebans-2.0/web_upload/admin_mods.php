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
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_MODS', 'ADMIN_DELETE_MODS', 'ADMIN_EDIT_MODS', 'ADMIN_LIST_MODS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      switch($_POST['action'])
      {
        case 'add':
          ModsWriter::add($_POST['name'], $_POST['folder'], $_POST['icon'], isset($_POST['enabled']));
          break;
        default:
          throw new Exception('Invalid action specified.');
      }
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
  
  $page->assign('permission_add_mods',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_MODS')));
  $page->assign('permission_delete_mods', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_MODS')));
  $page->assign('permission_edit_mods',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_MODS')));
  $page->assign('permission_list_mods',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_MODS')));
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