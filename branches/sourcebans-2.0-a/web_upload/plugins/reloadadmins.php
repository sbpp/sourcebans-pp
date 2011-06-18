<?php
require_once BASE_PATH . 'api.php';

class SB_ReloadAdmins extends SBPlugin
{
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

new SB_ReloadAdmins('Reload Admins', 'Peace-Maker', 'Reloads the server\'s admin cache when an admin or group is edited from the webpanel.', SB_VERSION, 'http://www.sourcebans.net');
?>