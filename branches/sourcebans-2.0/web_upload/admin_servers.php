<?php
require_once 'init.php';
require_once READERS_DIR . 'counts.php';
require_once READERS_DIR . 'groups.php';
require_once READERS_DIR . 'mods.php';
require_once READERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'servers.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['servers']);

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_SERVERS', 'ADMIN_DELETE_SERVERS', 'ADMIN_EDIT_SERVERS', 'ADMIN_LIST_SERVERS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      switch($_POST['action'])
      {
        case 'add':
          if($_POST['rcon'] != $_POST['rcon_confirm'])
              throw new Exception('The passwords don\'t match.');
          
          ServersWriter::add($_POST['ip'], $_POST['port'], $_POST['rcon'], $_POST['mod'], isset($_POST['enabled']), $_POST['groups']);
          break;
        case 'import':
          ServersWriter::import($_FILES['file']['name'], $_FILES['file']['tmp_name']);
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
  
  $counts_reader       = new CountsReader();
  $groups_reader       = new GroupsReader();
  $mods_reader         = new ModsReader();
  $servers_reader      = new ServersReader();
  
  $groups_reader->type = SERVER_GROUPS;
  $counts              = $counts_reader->executeCached(ONE_MINUTE  * 5);
  $groups              = $groups_reader->executeCached(ONE_MINUTE  * 5);
  $mods                = $mods_reader->executeCached(ONE_DAY);
  $servers             = $servers_reader->executeCached(ONE_MINUTE * 5);
  
  $page->assign('permission_config',         $userbank->HasAccess(array('ADMIN_OWNER')));
  $page->assign('permission_rcon',           $userbank->HasAccess(SM_RCON . SM_ROOT));
  $page->assign('permission_add_servers',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_SERVERS')));
  $page->assign('permission_delete_servers', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_SERVERS')));
  $page->assign('permission_edit_servers',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_SERVERS')));
  $page->assign('permission_import_servers', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_SERVERS')));
  $page->assign('permission_list_servers',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_SERVERS')));
  $page->assign('server_groups',             $groups);
  $page->assign('mods',                      $mods);
  $page->assign('servers',                   $servers);
  $page->assign('server_count',              $counts['servers']);
  $page->display('page_admin_servers');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>