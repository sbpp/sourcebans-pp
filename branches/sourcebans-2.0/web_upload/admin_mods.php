<?php
require_once 'api.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['mods'], !isset($_GET['nofullpage']));

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
          
          SB_API::addMod($_POST['name'], $_POST['folder'], $_POST['icon']);
          break;
        default:
          throw new Exception($phrases['invalid_action']);
      }
      
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
  
  $page->assign('permission_add_mods',    $userbank->HasAccess(array('OWNER', 'ADD_MODS')));
  $page->assign('permission_delete_mods', $userbank->HasAccess(array('OWNER', 'DELETE_MODS')));
  $page->assign('permission_edit_mods',   $userbank->HasAccess(array('OWNER', 'EDIT_MODS')));
  $page->assign('permission_list_mods',   $userbank->HasAccess(array('OWNER', 'LIST_MODS')));
  $page->assign('mods',                   SB_API::getMods());
  $page->display('page_admin_mods');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>