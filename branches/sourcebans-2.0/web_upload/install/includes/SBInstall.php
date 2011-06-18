<?php
/**
 * SourceBans installer
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Install
 * @version    $Id$
 */
class SBInstall extends Install
{
  public static function importAmxBans($host, $port, $user, $pass, $name, $prefix)
  {
    $db = new AdoDatabase($user, $pass, $name, $host . ':' . $port);
    
    // Import bans
    $data = DatabaseQuery::create($db)
      ->select()
      ->from($prefix . '_bans')
      ->fetchAll();
    
    foreach($data as $row)
    {
      $ban              = SB_API::createBan();
      $ban->admin_ip    = $row['admin_ip'];
      $ban->ip          = $row['player_ip'];
      $ban->length      = $row['ban_length'] / 60;
      $ban->name        = $row['player_nick'];
      $ban->reason      = $row['ban_reason'];
      $ban->steam       = $row['player_id'];
      $ban->insert_time = $row['ban_created'];
      $ban->save();
    }
  }
  
  public static function setupOwner($username, $password, $confirm_password, $email, $auth, $identity)
  {
    require_once SITE_DIR . 'api.php';
    
    if($password != $confirm_password)
      throw new Exception(SB_API::getLanguagePhrase('passwords_do_not_match'));
    
    // Add group
    $web_group       = SB_API::createWebGroup();
    $web_group->name = 'Owner';
    
    // Add owner permission to group
    $web_group->setPermissions(array('Owner'));
    $web_group->save();
    
    // Add admin
    $admin            = SB_API::createAdmin();
    $admin->auth      = $auth;
    $admin->email     = $email;
    $admin->identity  = $auth;
    $admin->name      = $username;
    $admin->password  = self::$_registry->admins->encrypt_password($password);
    $admin->web_group = $web_group;
    $admin->save();
  }
}