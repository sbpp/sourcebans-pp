<?php
/**
 * =============================================================================
 * Main user handler
 * 
 * @author InterWave Studios
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: CUserManager.php 65 2008-05-20 22:16:44Z olly $
 * =============================================================================
 */
require_once READERS_DIR . 'admins.php';
require_once READERS_DIR . 'permissions.php';

class CUserManager
{
  private $id    = -1;
  private $users = array();
  
  /**
   * Class constructor
   *
   * @param $id the current user's id
   * @param $password the current user's password
   * @return noreturn.
   */
  public function __construct($id = -2, $password = '')
  {
    if($id       == -2 && isset($_COOKIE['sb_admin_id']))
      $id       = $_COOKIE['sb_admin_id'];
    if($password == '' && isset($_COOKIE['sb_password']))
      $password = $_COOKIE['sb_password'];
    
    $admins_reader = new AdminsReader();
    $admins        = $admins_reader->executeCached(ONE_MINUTE * 5);
    
    $this->users   = $admins['list'];
    $this->id      = $this->CheckLogin($password, $id) ? $id : -1;
  }
  
  
  /**
   * Will check to see if an admin has any of the flags given
   *
   * @param $flags The flags to check for.
   * @param $id The ID of the user to check flags for.
   * @return boolean.
   */
  public function HasAccess($flags, $id = -2)
  {
    if($id == -2)
      $id = $this->id;
    if(empty($flags) || $id < 0)
      return false;
    
    if(is_array($flags))
    {
      foreach($flags as $flag)
      {
        if(in_array($flag, $this->users[$id]['web_flags']))
          return true;
      }
    }
    else
    {
      for($i = 0; $i < strlen($flags); $i++)
      {
        if(strpos($this->users[$id]['srv_flags'], $flags[$i]) !== false)
          return true;
      }
    }
    
    return false;
  }  
  
  /**
   * Gets a 'property' from the user array eg. 'steam'
   *
   * @param $id the ID of the user to get info for.
   * @return mixed.
   */
  public function GetProperty($name, $id = -2)
  {
    if($id == -2)
      $id = $this->id;
    if(empty($name) || $id < 0)
      return false;
    
    return $this->users[$id][$name];
  }
  
  
  /**
   * Will test the user's login stuff to check if they havnt changed their 
   * cookies or something along those lines.
   *
   * @param $password The admins password.
   * @param $id the current user's id
   * @return boolean.
   */
  public function CheckLogin($password, $id)
  {
    if(isset($this->users[$id]) && !empty($password) && $password == $this->users[$id]['password'])
    {
      $db = SBConfig::getEnv('db');
      $db->Execute('UPDATE ' . SBConfig::getEnv('prefix') . '_admins SET lastvisit = UNIX_TIMESTAMP() WHERE id = ?', array($id));
      return true;
    }
    else 
      return false;
  }
  
  
  public function login($username, $password, $save = true)
  {
    $db = SBConfig::getEnv('db');
    $id = $db->GetOne('SELECT id FROM ' . SBConfig::getEnv('prefix') . '_admins WHERE name = ?', array($username));
    if($this->CheckLogin($this->encrypt_password($password), $id))
    {
      $expire = $save ? time() + LOGIN_COOKIE_LIFETIME : 0;
      setcookie('sb_admin_id', $id,                                $expire);
      setcookie('sb_password', $this->encrypt_password($password), $expire);
      
      SBPlugins::call('OnLoginSuccess', $id, $username, $password);
      return true;
    }
    else
    {
      SBPlugins::call('OnLoginFailure', $id, $username, $password);
      return false;
    }
  }
  
  
  public function logout()
  {
    setcookie('sb_admin_id', null, time() - LOGIN_COOKIE_LIFETIME);
    setcookie('sb_password', null, time() - LOGIN_COOKIE_LIFETIME);
    
    SBPlugins::call('OnLogout');
  }
  
  
  /**
   * Encrypts a password.
   *
   * @param $password password to encrypt.
   * @param $salt salt to use
   * @return string.
   */
  public function encrypt_password($password, $salt = SB_SALT)
  {
    return sha1(sha1($salt . $password));
  }
  
  
  public function is_logged_in()
  {
    return $this->id != -1 ? true : false;
  }
  
  
  public function is_admin($id = -2)
  {
    $permissions_reader = new PermissionsReader();
    $permissions        = $permissions_reader->executeCached(ONE_DAY);
    
    return $this->HasAccess($permissions, $id == -2 ? $this->id : $id);
  }
  
  
  public function GetID()
  {
    return $this->id;
  }
}
?>