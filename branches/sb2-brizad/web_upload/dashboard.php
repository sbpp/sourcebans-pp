<?php
require_once 'api.php';

$config  = Env::get('config');
$phrases = Env::get('phrases');
$page    = new Page($phrases['dashboard'], !isset($_GET['nofullpage']));

try
{
  $bans    = SB_API::getBans(false, 10);
  $blocks  = SB_API::getBlocks(10);
  $servers = SB_API::getServers();
  
  $order   = isset($_GET['order']) && is_string($_GET['order']) ? $_GET['order'] : 'asc';
  $sort    = isset($_GET['sort'])  && is_string($_GET['sort'])  ? $_GET['sort']  : 'mod_name';
  
  Util::array_qsort($servers, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC);
  
  $page->assign('dashboard_text',  $config['dash.intro.text']);
  $page->assign('dashboard_title', $config['dash.intro.title']);
  $page->assign('log_nopopup',     $config['dash.lognopopup']);
  $page->assign('bans',            $bans['list']);
  $page->assign('blocks',          $blocks['list']);
  $page->assign('servers',         $servers);
  $page->assign('order',           $order);
  $page->assign('sort',            $sort);
  $page->assign('total_bans',      $bans['count']);
  $page->assign('total_blocks',    $blocks['count']);
  $page->display('page_dashboard');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>