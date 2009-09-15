<?php
require_once 'init.php';
require_once READERS_DIR . 'counts.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['administration']);

try
{
  if(!$userbank->is_admin())
    throw new Exception($phrases['access_denied']);
  
  $counts_reader = new CountsReader();
  
  $counts        = $counts_reader->executeCached(ONE_MINUTE * 5);
  $demosize      = Util::getDirectorySize(DEMOS_DIR);
  
  $page->assign('demosize',                   Util::formatSize($demosize['size']));
  $page->assign('total_admins',               $counts['admins']);
  $page->assign('total_archived_protests',    $counts['archived_protests']);
  $page->assign('total_archived_submissions', $counts['archived_submissions']);
  $page->assign('total_bans',                 $counts['bans']);
  $page->assign('total_blocks',               $counts['blocks']);
  $page->assign('total_servers',              $counts['servers']);
  $page->assign('total_protests',             $counts['protests']);
  $page->assign('total_submissions',          $counts['submissions']);
  $page->display('page_admin');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>