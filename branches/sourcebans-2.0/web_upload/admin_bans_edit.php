<?php
require_once 'init.php';
require_once READERS_DIR . 'bans.php';
require_once WRITERS_DIR . 'bans.php';

$userbank = Env::get('userbank');
$phrases  = Env::get('phrases');
$page     = new Page(ucwords($phrases['edit_ban']));

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ALL_BANS', 'ADMIN_EDIT_GROUP_BANS', 'ADMIN_EDIT_OWN_BANS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    BansWriter::edit($_POST['id'], $_POST['name'], $_POST['type'], $_POST['steam'], $_POST['ip'], $_POST['length'], $_POST['reason']);
    
    Util::redirect();
  }
  
  $bans_reader = new BansReader();
  $bans        = $bans_reader->executeCached(ONE_MINUTE * 5);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($bans['list'][$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $id          = $_GET['id'];
  
  $page->assign('ban_name',   $bans['list'][$id]['name']);
  $page->assign('ban_type',   $bans['list'][$id]['type']);
  $page->assign('ban_steam',  $bans['list'][$id]['steam']);
  $page->assign('ban_ip',     $bans['list'][$id]['ip']);
  $page->assign('ban_length', $bans['list'][$id]['length']);
  $page->assign('ban_reason', $bans['list'][$id]['reason']);
  $page->display('page_admin_bans_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>