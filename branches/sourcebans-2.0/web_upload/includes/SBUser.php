<?php
/**
 * SourceBans user
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage User
 * @version    $Id$
 */
class SBUser extends User
{
  function __construct(Session &$session)
  {
    parent::__construct($session);
    
    if(!isset($this->_session->sb_admin_id, $this->_session->sb_password))
      return;
    
    $this->_data = $this->_registry->admins[$this->_session->sb_admin_id];
    if($this->password != $this->_session->sb_password)
    {
      $this->_data = null;
    }
    if(!$this->is_logged_in())
      return;
    
    $this->visit_time = time();
    $this->_data->save();
    
    //$this->_language = new SBLanguage($this->_language);
    //$this->_theme    = new SBTheme($this->_theme);
  }
  
  
  public function hasAccess($flags)
  {
    if(!$this->is_logged_in())
      return false;
    
    return $this->_data->hasAccess($flags);
  }
  
  public function is_admin()
  {
    return $this->hasAccess(/*$this->_registry->all_web*/true);
  }
  
  public function login($username, $password, $remember = true)
  {
    $password    = $this->_registry->admins->encrypt_password($password);
    $this->_data = null;
    
    if(!$this->_login('name', $username, $password))
      return false;
    
    $this->_session->sb_admin_id = $this->id;
    $this->_session->sb_password = $this->password;
    
    if($remember)
    {
      $lifetime = time() + $this->_registry->session_lifetime;
      
      setcookie('sb_admin_id', $this->id,       $lifetime);
      setcookie('sb_password', $this->password, $lifetime);
    }
    
    return true;
  }
  
  public function logout()
  {
    $this->_data = null;
    
    unset($this->_session->sb_admin_id);
    unset($this->_session->sb_password);
    
    if(isset($_COOKIE['sb_admin_id']))
    {
      unset($_COOKIE['sb_admin_id']);
    }
    if(isset($_COOKIE['sb_password']))
    {
      unset($_COOKIE['sb_password']);
    }
  }
  
  
  protected function _login($name, $value, $password)
  {
    foreach($this->_registry->admins as $admin)
    {
      if($admin->$name    != $value ||
         $admin->password != $password)
        continue;
      
      $this->_data = $admin;
      return true;
    }
    
    return false;
  }
}