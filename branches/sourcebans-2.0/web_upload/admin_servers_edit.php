<?php
require_once 'api.php';

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
      SB_API::editServer($_POST['id'], $_POST['name'], $_POST['icon'], $_POST['folder'], isset($_POST['enabled']), $_POST['groups']);
      
      exit(json_encode(array(
        'redirect' => Util::buildUrl(array(
          '_' => 'admin_servers.php'
        ))
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $server = SB_API::getServer($_GET['id']);
  
  $page->assign('server_ip',      $server['ip']);
  $page->assign('server_port',    $server['port']);
  $page->assign('server_rcon',    $server['rcon']);
  $page->assign('server_mod',     $server['mod_id']);
  $page->assign('server_enabled', $server['enabled']);
  $page->assign('server_groups',  $server['groups']);
  $page->assign('groups',         SB_API::getGroups(SERVER_GROUPS));
  $page->assign('mods',           SB_API::getMods());
  $page->display('page_admin_servers_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>