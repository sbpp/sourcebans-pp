<?php
require_once 'init.php';
require_once READERS_DIR . 'groups.php';
require_once READERS_DIR . 'mods.php';
require_once READERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'servers.php';

$userbank = Env::get('userbank');
$phrases  = Env::get('phrases');
$page     = new Page(ucwords($phrases['edit_server']));

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_SERVERS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    ServersWriter::edit($_POST['id'], $_POST['name'], $_POST['icon'], $_POST['folder'], isset($_POST['enabled']), $_POST['groups']);
    
    Util::redirect();
  }
  
  $groups_reader       = new GroupsReader();
  $mods_reader         = new ModsReader();
  $servers_reader      = new ServersReader();
  
  $groups_reader->type = SERVER_GROUPS;
  $server_groups       = $groups_reader->executeCached(ONE_MINUTE  * 5);
  $mods                = $mods_reader->executeCached(ONE_DAY);
  $servers             = $servers_reader->executeCached(ONE_MINUTE * 5);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($servers[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $server              = $servers[$_GET['id']];
  
  $page->assign('server_ip',      $server['ip']);
  $page->assign('server_port',    $server['port']);
  $page->assign('server_rcon',    $server['rcon']);
  $page->assign('server_mod',     $server['mod_id']);
  $page->assign('server_enabled', $server['enabled']);
  $page->assign('server_groups',  $server['groups']);
  $page->assign('mods',           $mods);
  $page->assign('server_groups',  $server_groups);
  $page->display('page_admin_servers_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>