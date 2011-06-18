<?php
/**
 * SourceBans template
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Template
 * @version    $Id$
 */
class SBTemplate extends SmartyTemplate
{
  private $_admin_panes = array();
  private $_admin_tabs  = array();
  private $_tabs        = array();
  
  
  public function addAdminPane($id, $content)
  {
    $pane          = new SBPane();
    $pane->content = $content;
    $pane->id      = $id;
    
    $this->_admin_panes[] = $pane;
  }
  
  public function addAdminTab($name, $uri, $id = null)
  {
    $tab       = new SBTab();
    $tab->id   = $id;
    $tab->name = $name;
    $tab->uri  = $uri;
    
    $this->_admin_tabs[] = $tab;
  }
  
  public function addTab($name, $description, $uri)
  {
    $tab              = new SBTab();
    $tab->description = $description;
    $tab->name        = $name;
    $tab->uri         = $uri;
    
    $this->_tabs[] = $tab;
  }
  
  public function display($file)
  {
    // Call OnDisplayTemplate hook, ignoring the return value
    list(, $template) = $this->_registry->plugins->OnDisplayTemplate($this, $file);
    foreach(get_object_vars($template) as $name => $value)
      $this->$name = $value;
    
    $this->_scripts     = $template->getScripts();
    $this->_styles      = $template->getStyles();
    //$this->_admin_panes = $template->getAdminPanes();
    //$this->_admin_tabs  = $template->getAdminTabs();
    //$this->_tabs        = $template->getTabs();
    
    $user                      = &$this->_registry->user;
    $user->permission_admins   = $user->hasAccess(array('OWNER', 'ADD_ADMINS',  'DELETE_ADMINS',  'EDIT_ADMINS',     'LIST_ADMINS'));
    $user->permission_bans     = $user->hasAccess(array('OWNER', 'ADD_BANS',    'EDIT_ALL_BANS',  'EDIT_GROUP_BANS', 'EDIT_OWN_BANS', 'LIST_BANS', 'BAN_PROTESTS', 'BAN_SUBMISSIONS'));
    $user->permission_groups   = $user->hasAccess(array('OWNER', 'ADD_GROUPS',  'DELETE_GROUPS',  'EDIT_GROUPS',     'LIST_GROUPS'));
    $user->permission_games    = $user->hasAccess(array('OWNER', 'ADD_GAMES',   'DELETE_GAMES',   'EDIT_GAMES',      'LIST_GAMES'));
    $user->permission_servers  = $user->hasAccess(array('OWNER', 'ADD_SERVERS', 'DELETE_SERVERS', 'EDIT_SERVERS',    'LIST_SERVERS'));
    $user->permission_settings = $user->hasAccess(array('OWNER', 'SETTINGS'));
    
    $this->admin_panes = $this->_admin_panes;
    $this->admin_tabs  = $this->_admin_tabs;
    $this->sb_quote    = $this->_registry->quotes->getRandom();
    $this->sb_version  = $this->_registry->sb_version;
    $this->tabs        = $this->_tabs;
    
    // DEBUG
    $start_time = explode(' ', START_TIME);
    $end_time   = explode(' ', microtime());
    $this->gen_time = ($end_time[0] + $end_time[1]) - ($start_time[0] + $start_time[1]);
    // DEBUG
    
    parent::display($file);
  }
}