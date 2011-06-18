<?php
class DashboardController extends BaseController
{
  protected function _title()
  {
    return $this->_registry->user->language->dashboard;
  }
  
  
  public function index()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    
    $bans    = $this->_registry->bans;
    $blocks  = $this->_registry->blocks;
    $servers = $this->_registry->servers;
    
    $order   = isset($uri->order) ? $uri->order : 'asc';
    $sort    = isset($uri->sort)  ? $uri->sort  : 'game';
    
    /*Util::object_qsort($bans,    'insert_time', SORT_DESC);
    Util::object_qsort($blocks,  'insert_time', SORT_DESC);
    Util::object_qsort($servers, $sort,  $order == 'desc' ? SORT_DESC : SORT_ASC);
    
    $template->bans         = array_slice($bans,   0, 10);
    $template->blocks       = array_slice($blocks, 0, 10);*/
    $template->bans         = $bans;
    $template->blocks       = $blocks;
    $template->servers      = $servers;
    $template->order        = $order;
    $template->sort         = $sort;
    $template->total_bans   = count($bans);
    $template->total_blocks = count($blocks);
    $template->display('dashboard');
  }
}