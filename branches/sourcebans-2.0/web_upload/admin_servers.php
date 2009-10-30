<?php
require_once 'init.php';
require_once READERS_DIR . 'groups.php';
require_once READERS_DIR . 'mods.php';
require_once READERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'servers.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
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
          
          ServersWriter::add($_POST['ip'], $_POST['port'], $_POST['rcon'], $_POST['mod'], isset($_POST['enabled']), $_POST['groups']);
          break;
        case 'import':
          if(!$userbank->HasAccess(array('OWNER', 'IMPORT_SERVERS')))
            throw new Exception($phrases['access_denied']);
          
          ServersWriter::import($_FILES['file']['name'], $_FILES['file']['tmp_name']);
          break;
        default:
          throw new Exception($phrases['invalid_action']);
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
  
  $groups_reader       = new GroupsReader();
  $mods_reader         = new ModsReader();
  $servers_reader      = new ServersReader();
  
  $groups_reader->type = SERVER_GROUPS;
  $groups              = $groups_reader->executeCached(ONE_MINUTE * 5);
  $mods                = $mods_reader->executeCached(ONE_DAY);
  $servers             = $servers_reader->executeCached(ONE_MINUTE);
  
  $page->assign('permission_config',         $userbank->HasAccess(array('OWNER')));
  $page->assign('permission_rcon',           $userbank->HasAccess(SM_RCON . SM_ROOT));
  $page->assign('permission_add_servers',    $userbank->HasAccess(array('OWNER', 'ADD_SERVERS')));
  $page->assign('permission_delete_servers', $userbank->HasAccess(array('OWNER', 'DELETE_SERVERS')));
  $page->assign('permission_edit_servers',   $userbank->HasAccess(array('OWNER', 'EDIT_SERVERS')));
  $page->assign('permission_import_servers', $userbank->HasAccess(array('OWNER', 'IMPORT_SERVERS')));
  $page->assign('permission_list_servers',   $userbank->HasAccess(array('OWNER', 'LIST_SERVERS')));
  $page->assign('server_groups',             $groups);
  $page->assign('mods',                      $mods);
  $page->assign('servers',                   $servers);
  $page->display('page_admin_servers');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>