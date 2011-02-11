<?php
require_once 'api.php';

$phrases  = SBConfig::getEnv('phrases');
$userbank = SBConfig::getEnv('userbank');
$page     = new Page($phrases['servers'], !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'ADD_SERVERS', 'DELETE_SERVERS', 'EDIT_SERVERS', 'LIST_SERVERS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      switch($_POST['action'])
      {
        case 'add':
          if(!$userbank->HasAccess(array('OWNER', 'ADD_SERVERS')))
            throw new Exception($phrases['access_denied']);
          if($_POST['rcon'] != $_POST['rcon_confirm'])
              throw new Exception($phrases['passwords_do_not_match']);
          
          SB_API::addServer($_POST['ip'], $_POST['port'], $_POST['rcon'], $_POST['mod'], isset($_POST['enabled']), $_POST['groups']);
          break;
        case 'import':
          if(!$userbank->HasAccess(array('OWNER', 'IMPORT_SERVERS')))
            throw new Exception($phrases['access_denied']);
          
          SB_API::importServers($_FILES['file']['name'], $_FILES['file']['tmp_name']);
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
  
  $page->assign('permission_config',         $userbank->HasAccess(array('OWNER')));
  $page->assign('permission_rcon',           $userbank->HasAccess(SM_RCON . SM_ROOT));
  $page->assign('permission_add_servers',    $userbank->HasAccess(array('OWNER', 'ADD_SERVERS')));
  $page->assign('permission_delete_servers', $userbank->HasAccess(array('OWNER', 'DELETE_SERVERS')));
  $page->assign('permission_edit_servers',   $userbank->HasAccess(array('OWNER', 'EDIT_SERVERS')));
  $page->assign('permission_import_servers', $userbank->HasAccess(array('OWNER', 'IMPORT_SERVERS')));
  $page->assign('permission_list_servers',   $userbank->HasAccess(array('OWNER', 'LIST_SERVERS')));
  $page->assign('server_groups',             SB_API::getGroups(SERVER_GROUPS));
  $page->assign('mods',                      SB_API::getMods());
  $page->assign('servers',                   SB_API::getServers());
  $page->display('page_admin_servers');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>