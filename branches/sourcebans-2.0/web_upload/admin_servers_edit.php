<?php
require_once 'init.php';
require_once READERS_DIR . 'groups.php';
require_once READERS_DIR . 'mods.php';
require_once READERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'servers.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['edit_server']), !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'EDIT_SERVERS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      ServersWriter::edit($_POST['id'], $_POST['ip'], $_POST['port'], $_POST['rcon'] == 'xxxxxxxxxx' ? null : $_POST['rcon'], $_POST['mod'], isset($_POST['enabled']), $_POST['groups']);
      
      exit(json_encode(array(
        'redirect' => 'admins_servers.php'
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
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($servers[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $server              = $servers[$_GET['id']];
  
  $page->assign('server_ip',      $server['ip']);
  $page->assign('server_port',    $server['port']);
  $page->assign('server_rcon',    $server['rcon']);
  $page->assign('server_mod',     $server['mod_id']);
  $page->assign('server_enabled', $server['enabled']);
  $page->assign('server_groups',  $server['groups']);
  $page->assign('groups',         $groups);
  $page->assign('mods',           $mods);
  $page->display('page_admin_servers_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>