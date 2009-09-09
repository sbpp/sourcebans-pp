<?php
require_once 'init.php';
require_once READERS_DIR . 'bans.php';
require_once READERS_DIR . 'blocks.php';
require_once READERS_DIR . 'counts.php';
require_once READERS_DIR . 'servers.php';

$config  = Env::get('config');
$phrases = Env::get('phrases');
$page    = new Page(ucwords($phrases['dashboard']));

try
{
  $bans_reader          = new BansReader();
  $blocks_reader        = new BlocksReader();
  $counts_reader        = new CountsReader();
  $servers_reader       = new ServersReader();
  
  $bans_reader->limit   = 10;
  $blocks_reader->limit = 10;
  
  if(isset($_GET['sort']) && is_string($_GET['sort']))
    $servers_reader->sort = $_GET['sort'];
  
  $bans                 = $bans_reader->executeCached(ONE_MINUTE    * 5);
  $blocks               = $blocks_reader->executeCached(ONE_MINUTE  * 5);
  $counts               = $counts_reader->executeCached(ONE_MINUTE  * 5);
  $servers              = $servers_reader->executeCached(ONE_MINUTE * 5);
  
  $page->assign('dashboard_text',  $config['dash.intro.text']);
  $page->assign('dashboard_title', $config['dash.intro.title']);
  $page->assign('log_nopopup',     $config['dash.lognopopup']);
  $page->assign('bans',            $bans['list']);
  $page->assign('blocks',          $blocks);
  $page->assign('servers',         $servers);
  $page->assign('total_bans',      $counts['bans']);
  $page->assign('total_blocks',    $counts['blocks']);
  $page->display('page_dashboard');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>