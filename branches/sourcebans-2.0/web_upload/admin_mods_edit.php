<?php
require_once 'init.php';
require_once READERS_DIR . 'mods.php';
require_once WRITERS_DIR . 'mods.php';

$userbank = Env::get('userbank');
$phrases  = Env::get('phrases');
$page     = new Page(ucwords($phrases['edit_mod']));

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_MODS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    ModsWriter::edit($_POST['id'], $_POST['name'], $_POST['folder'], $_POST['icon'], isset($_POST['enabled']));
    
    Util::redirect();
  }
  
  $mods_reader = new ModsReader();
  $mods        = $mods_reader->executeCached(ONE_DAY);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($mods[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $id          = $_GET['id'];
  
  $page->assign('mod_name',    $mods[$id]['name']);
  $page->assign('mod_folder',  $mods[$id]['folder']);
  $page->assign('mod_icon',    $mods[$id]['icon']);
  $page->assign('mod_enabled', $mods[$id]['enabled'] == 1);
  $page->display('page_admin_mods_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>