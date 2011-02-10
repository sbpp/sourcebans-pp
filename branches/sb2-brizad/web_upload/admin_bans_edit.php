<?php
require_once 'api.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['edit_ban']), !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'EDIT_ALL_BANS', 'EDIT_GROUP_BANS', 'EDIT_OWN_BANS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      SB_API::editBan($_POST['id'], $_POST['type'], strtoupper($_POST['steam']), $_POST['ip'], $_POST['name'], $_POST['reason'] == 'other' ? $_POST['reason_other'] : $_POST['reason'], $_POST['length']);
      
      // If one or more demos were uploaded, add them
      foreach($_FILES['demo'] as $demo)
        SB_API::addDemo($_POST['id'], BAN_TYPE, $demo['name'], $demo['tmp_name']);
      
      exit(json_encode(array(
        'redirect' => Util::buildUrl(array(
          '_' => 'admin_bans.php'
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
  
  $ban = SB_API::getBan($_GET['id']);
  
  $page->assign('ban_type',   $ban['type']);
  $page->assign('ban_steam',  $ban['steam']);
  $page->assign('ban_ip',     $ban['ip']);
  $page->assign('ban_name',   $ban['name']);
  $page->assign('ban_reason', $ban['reason']);
  $page->assign('ban_length', $ban['length']);
  $page->assign('ban_demos',  $ban['demos']);
  $page->display('page_admin_bans_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>