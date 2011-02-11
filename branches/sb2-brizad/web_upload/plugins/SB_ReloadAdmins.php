<?php
require_once BASE_PATH . 'api.php';

class SB_ReloadAdmins extends SBPlugin
{
  public $name = 'Reload Admins';
  public $author = 'Peace-Maker';
  public $desc = 'Reloads the server\'s admin cache when an admin or group is edited from the webpanel.';
  public $version = SB_VERSION;
  public $url = 'http://www.sourcebans.net';

  public static function OnAddAdmin($id, $name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group)
  {
    SB_API::sendRCON('sm_reloadadmins');
  }
  
  
  public static function OnDeleteAdmin($id)
  {
    SB_API::sendRCON('sm_reloadadmins');
  }
  
  
  public static function OnEditAdmin($id, $name, $auth, $identity, $email, $password, $srv_password, $srv_groups, $web_group)
  {
    SB_API::sendRCON('sm_reloadadmins');
  }
  
  
  public static function OnAddGroup($id, $type, $name, $flags, $immunity)
  {
    SB_API::sendRCON('sm_reloadadmins');
  }
  
  
  public static function OnDeleteGroup($id, $type)
  {
    SB_API::sendRCON('sm_reloadadmins');
  }
  
  
  public static function OnEditGroup($id, $type, $name, $flags, $immunity)
  {
    SB_API::sendRCON('sm_reloadadmins');
  }
}
