<?php
require_once 'api.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['administration'], !isset($_GET['nofullpage']));

try
{
  if(!$userbank->is_admin())
    throw new Exception($phrases['access_denied']);
  
  $admins      = SB_API::getAdmins();
  $bans        = SB_API::getBans();
  $blocks      = SB_API::getBlocks();
  $protests    = SB_API::getProtests();
  $servers     = SB_API::getServers();
  $submissions = SB_API::getSubmissions();
  $demosize    = Util::getDirectorySize(DEMOS_DIR);
  
  $page->assign('demosize',                   Util::formatSize($demosize['size']));
  $page->assign('total_admins',               $admins['count']);
  $page->assign('total_archived_protests',    $archived_protests['count']);
  $page->assign('total_archived_submissions', $archived_submissions['count']);
  $page->assign('total_bans',                 $bans['count']);
  $page->assign('total_blocks',               $blocks['count']);
  $page->assign('total_protests',             $protests['count']);
  $page->assign('total_servers',              count($servers));
  $page->assign('total_submissions',          $submissions['count']);
  $page->display('page_admin');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>