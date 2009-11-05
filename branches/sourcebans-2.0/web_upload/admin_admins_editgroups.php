<?php
require_once 'api.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['edit_groups']), !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'EDIT_ADMINS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      SB_API::editAdmin($_POST['id'], null, null, null, null, null, null, $_POST['srv_groups'], $_POST['web_group']);
      
      exit(json_encode(array(
        'redirect' => Util::buildUrl(array(
          '_' => 'admin_admins.php'
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
  
  $admin = SB_API::getAdmin($_GET['id']);
  
  $page->assign('admin_name',       $admin['name']);
  $page->assign('admin_srv_groups', $admin['srv_groups']);
  $page->assign('admin_web_group',  $admin['group_id']);
  $page->assign('server_groups',    SB_API::getGroups(SERVER_GROUPS));
  $page->assign('web_groups',       SB_API::getGroups(WEB_GROUPS));
  $page->display('page_admin_admins_editgroups');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>