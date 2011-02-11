<?php
require_once 'api.php';

$phrases = SBConfig::getEnv('phrases');

try
{
  $db   = SBConfig::getEnv('db');
  $demo = $db->GetRow('SELECT ban_id, type, filename
                       FROM   ' . SBConfig::getEnv('prefix') . '_demos
                       WHERE  id = ?',
                       array($_GET['id']));
  
  if(!file_exists(DEMOS_DIR . $demo['type'] . $demo['ban_id'] . '_' . $demo['filename']))
    throw new Exception($phrases['file_does_not_exist']);
  
  header('Content-type: application/force-download');
  header('Content-Transfer-Encoding: Binary');
  header('Content-disposition: attachment; filename="' . $demo['filename'] . '"');
  readfile(DEMOS_DIR . $demo['type'] . $demo['ban_id'] . '_' . $demo['filename']);
}
catch(Exception $e)
{
  $page = new Page($phrases['error']);
  
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>