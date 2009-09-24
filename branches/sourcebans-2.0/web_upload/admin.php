<?php
require_once 'init.php';
require_once READERS_DIR . 'admins.php';
require_once READERS_DIR . 'bans.php';
require_once READERS_DIR . 'blocks.php';
require_once READERS_DIR . 'servers.php';
require_once READERS_DIR . 'protests.php';
require_once READERS_DIR . 'submissions.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['administration'], !isset($_GET['nofullpage']));

try
{
  if(!$userbank->is_admin())
    throw new Exception($phrases['access_denied']);
  
  $admins_reader               = new AdminsReader();
  $bans_reader                 = new BansReader();
  $blocks_reader               = new BlocksReader();
  $protests_reader             = new ProtestsReader();
  $servers_reader              = new ServersReader();
  $submissions_reader          = new SubmissionsReader();
  
  $admins_reader->limit        =
  $bans_reader->limit          =
  $protests_reader->limit      =
  $submissions_reader->limit   = $config['banlist.bansperpage'];
  $blocks_reader->limit        = 10;
  
  $admins                      = $admins_reader->executeCached(ONE_MINUTE      * 5);
  $bans                        = $bans_reader->executeCached(ONE_MINUTE        * 5);
  $blocks                      = $blocks_reader->executeCached(ONE_MINUTE      * 5);
  $protests                    = $protests_reader->executeCached(ONE_MINUTE    * 5);
  $servers                     = $servers_reader->executeCached(ONE_MINUTE);
  $submissions                 = $submissions_reader->executeCached(ONE_MINUTE * 5);
  
  $protests_reader->archive    = true;
  $submissions_reader->archive = true;
  $archived_protests           = $protests_reader->executeCached(ONE_MINUTE    * 5);
  $archived_submissions        = $submissions_reader->executeCached(ONE_MINUTE * 5);
  
  $demosize                    = Util::getDirectorySize(DEMOS_DIR);
  
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