<?php
require_once 'init.php';

try
{
  $db   = Env::get('db');
  $demo = $db->GetRow('SELECT filename
                       FROM   ' . Env::get('prefix') . '_demos
                       WHERE  ban_id = ?
                         AND  type   = ?',
                       array($_GET['id'], $_GET['type']));
  
  if(!file_exists(DEMOS_DIR . $demo['filename']))
    throw new Exception('File not found.');
  
  header('Content-type: application/force-download');
  header('Content-Transfer-Encoding: Binary');
  header('Content-disposition: attachment; filename="' . $demo['filename'] . '"');
  readfile(DEMOS_DIR . $demo['filename']);
}
catch(Exception $e)
{
  $phrases = Env::get('phrases');
  $page    = new Page($phrases['error']);
  
  $page->assign('error', $e->getMessage());
  $page->display('page_error.tpl');
}
?>