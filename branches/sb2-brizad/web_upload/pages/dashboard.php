<?php
require_once 'api.php';

Page::addPage(new PageDashboard('dashboard'));

class PageDashboard extends Page
{
  public function load()
  {
    $config  = SBConfig::getEnv('config');
    $phrases = SBConfig::getEnv('phrases');

    $bans    = SB_API::getBans(false, 10);
    $blocks  = SB_API::getBlocks(10);
    $servers = SB_API::getServers();

    $order   = isset($_GET['order']) && is_string($_GET['order']) ? $_GET['order'] : 'asc';
    $sort    = isset($_GET['sort'])  && is_string($_GET['sort'])  ? $_GET['sort']  : 'mod_name';

    Util::array_qsort($servers, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC);

    $this->assign('dashboard_text',  $config['dash.intro.text']);
    $this->assign('dashboard_title', $config['dash.intro.title']);
    $this->assign('log_nopopup',     $config['dash.lognopopup']);
    $this->assign('bans',            $bans['list']);
    $this->assign('blocks',          $blocks['list']);
    $this->assign('servers',         $servers);
    $this->assign('order',           $order);
    $this->assign('sort',            $sort);
    $this->assign('total_bans',      $bans['count']);
    $this->assign('total_blocks',    $blocks['count']);
    $this->display('page_dashboard');

    return true;
  }
}

