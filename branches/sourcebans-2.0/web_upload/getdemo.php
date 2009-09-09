<?php
require_once 'init.php';

try
{
  $db   = Env::get('db');
  $demo = $db->GetRow('SELECT ban_id, type, filename
                       FROM   ' . Env::get('prefix') . '_demos
                       WHERE  id = ?',
                       array($_GET['id']));
  
  if(!file_exists(DEMOS_DIR . $demo['type'] . $demo['ban_id'] . '_' . $demo['filename']))
    throw new Exception('File not found.');
  
  header('Content-type: application/force-download');
  header('Content-Transfer-Encoding: Binary');
  header('Content-disposition: attachment; filename="' . $demo['filename'] . '"');
  readfile(DEMOS_DIR . $demo['type'] . $demo['ban_id'] . '_' . $demo['filename']);
}
catch(Exception $e)
{
  $phrases = Env::get('phrases');
  $page    = new Page($phrases['error']);
  
  $page->assign('error', $e->getMessage());
  $page->display('page_error.tpl');
}
?>