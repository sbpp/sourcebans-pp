<?php
/**
 * Main user handler
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
class CUserManager
{
  protected $aid = -1;
  protected $admins = array();
  
  /**
   * Class constructor
   *
   * @param $aid the current user's aid
   * @param $password the current user's password
   * @return noreturn.
   */
  function __construct($aid, $password)
  {
    if(!$this->CheckLogin($password, $aid))
      return;
    
    $this->aid = $aid;
    $this->GetUserArray($aid);
  }
  
  
  /**
   * Gets all user details from the database, saves them into
   * the admin array 'cache', and then returns the array
   *
   * @param $aid the ID of admin to get info for.
   * @return array.
   */
  public function GetUserArray($aid = -2)
  {
    if($aid == -2)
    {
      $aid = $this->aid;
    }
    
    $aid = (int)$aid;
    if($aid <= 0)
      return false;
    
    // We already got the data from the DB, and its saved in the manager
    if(isset($this->admins[$aid]) && !empty($this->admins[$aid]))
      return $this->admins[$aid];
    
    // Not in the manager, so we need to get them from DB
    $admin = $GLOBALS['db']->GetRow('SELECT adm.user, adm.authid, adm.password, adm.gid, adm.email, adm.validate, adm.extraflags,
                                            adm.immunity AS admimmunity, sg.immunity AS sgimmunity, adm.srv_password, adm.srv_group,
                                            adm.srv_flags, sg.flags AS sgflags, wg.flags AS wgflags, wg.name AS wgname, adm.lastvisit
                                     FROM      ' . DB_PREFIX . '_admins AS adm
                                     LEFT JOIN ' . DB_PREFIX . '_groups AS wg ON adm.gid = wg.gid
                                     LEFT JOIN ' . DB_PREFIX . '_srvgroups AS sg ON adm.srv_group = sg.name
                                     WHERE adm.aid = ?',
                                    array($aid));
    if(!$admin)  
      return false;  // ohnoes some type of db error
    
    $admin['extraflags']  |= $admin['wgflags'];
    $admin['srv_flags']   .= $admin['sgflags'];
    $admin['srv_immunity'] = ($admin['admimmunity'] > $admin['sgimmunity'] ? $admin['admimmunity'] : $admin['sgimmunity']);
    $admin['srv_groups']   = $admin['srv_group'];
    $admin['group_name']   = $admin['wgname'];
    
    $this->admins[$aid] = $admin;
    return $admin;
  }
  
  
  /**
   * Will check to see if an admin has any of the flags given
   *
   * @param $flags The flags to check for.
   * @param $aid The user to check flags for.
   * @return boolean.
   */
  public function HasAccess($flags, $aid = -2)
  {
    if($aid == -2)
    {
      $aid = $this->aid;
    }
    
    $aid = (int)$aid;
    if(empty($flags) || $aid <= 0)
      return false;
    
    if(!isset($this->admins[$aid]))
    {
      $this->GetUserArray($aid);
    }
    
    if(is_numeric($flags))
    {
      return ($this->admins[$aid]['extraflags'] & $flags);
    }
    else 
    {
      for($i = 0; $i < strlen($this->admins[$aid]['srv_flags']); $i++)
      {
        for($j = 0; $j < strlen($flags); $j++)
        {
          if(strpos($this->admins[$aid]['srv_flags'][$i], $flags[$j]) !== false)
            return true;
        }
      }
    }
  }
  
  
  /**
   * Gets a 'property' from the user array eg. 'authid'
   *
   * @param $aid the ID of admin to get info for.
   * @return mixed.
   */
  public function GetProperty($name, $aid = -2)
  {
    if($aid == -2)
    {
      $aid = $this->aid;
    }
    
    $aid = (int)$aid;  
    if(empty($name) || $aid <= 0)
      return false;
    
    if(!isset($this->admins[$aid]))
    {
      $this->GetUserArray($aid);
    }
    
    return $this->admins[$aid][$name];
  }
  
  
  /**
   * Will test the user's login stuff to check if they haven't changed their 
   * cookies or something along those lines.
   *
   * @param $password The admins password.
   * @param $aid the admins aid
   * @return boolean.
   */
  public function CheckLogin($password, $aid)
  {
    $aid = (int)$aid;
    
    if(empty($password))
      return false;
    if(!isset($this->admins[$aid]))
    {
      $this->GetUserArray($aid);
    }
    if($password != $this->admins[$aid]['password'])
      return false;
    
    $GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_admins
                             SET    lastvisit = UNIX_TIMESTAMP()
                             WHERE  aid = ?',
                            array($aid));
    return true;
  }
  
  
  public function login($aid, $password, $save = true)
  {
    if(!$this->CheckLogin($this->encrypt_password($password), $aid))
      return false;
    
    $expire = ($save ? time() + LOGIN_COOKIE_LIFETIME : null);
    setcookie('aid', $aid, $expire);
    setcookie('password', $this->encrypt_password($password), $expire);
    setcookie('user', isset($_SESSION['user']['user']) ? $_SESSION['user']['user'] : null, $expire);
    
    return true;
  }
  
  
  
  /**
   * Encrypts a password.
   *
   * @param $password password to encrypt.
   * @return string.
   */
  public function encrypt_password($password, $salt = SB_SALT)
  {
    return sha1(sha1($salt . $password));
  }
  
  
  public function is_logged_in()
  {
    return ($this->aid != -1);
  }
  
  
  public function is_admin($aid = -2)
  {
    if($aid == -2)
    {
      $aid = $this->aid;
    }
    
    return $this->HasAccess(ALL_WEB, $aid);
  }
  
  
  public function GetAid()
  {
    return $this->aid;
  }
  
  
  public function GetAllAdmins()
  {
    $admins = $GLOBALS['db']->GetAll('SELECT aid
                                      FROM ' . DB_PREFIX . '_admins');
    foreach($admins as $admin)
    {
      $this->GetUserArray($admin['aid']);
    }
    
    return $this->admins;
  }
  
  
  public function GetAdmin($aid = -2)
  {
    if($aid == -2)
    {
      $aid = $this->aid;
    }
    
    $aid = (int)$aid;
    if($aid <= 0)
      return false;
    
    if(!isset($this->admins[$aid]))
    {
      $this->GetUserArray($aid);
    }
    
    return $this->admins[$aid];
  }
  
  
  public function AddAdmin($name, $steam, $password, $email, $web_group, $web_flags, $srv_group, $srv_flags, $immunity, $srv_password)
  {    
    $add_admin = $GLOBALS['db']->Execute('INSERT INTO ' . DB_PREFIX . '_admins(user, authid, password, gid, email, validate, extraflags, immunity, srv_group, srv_flags, srv_password)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                                         array($name, $steam, $this->encrypt_password($password), $web_group, $email, 0, $web_flags, $immunity, $srv_group, $srv_flags, $srv_password));
    
    return ($add_admin ? $GLOBALS['db']->Insert_ID() : -1);
  }
}
?>
