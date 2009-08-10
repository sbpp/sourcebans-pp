<?php
require_once 'init.php';
require_once READERS_DIR . 'groups.php';
require_once WRITERS_DIR . 'groups.php';
require_once READERS_DIR . 'permissions.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['groups']);

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_GROUPS', 'ADMIN_DELETE_GROUPS', 'ADMIN_EDIT_GROUPS', 'ADMIN_LIST_GROUPS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    switch($_POST['action'])
    {
      case 'add':
        // Parse flags depending on group type
        switch($_POST['type'])
        {
          case SERVER_ADMIN_GROUPS:
            // If flag array contains root flag, only pass root flag, otherwise create flag string
            $flags = in_array(SM_ROOT,       $_POST['srv_flags']) ? SM_ROOT              : implode($_POST['srv_flags']);
            break;
          case WEB_ADMIN_GROUPS:
            // If flag array contains owner flag, only pass owner flag, otherwise pass entire flag array
            $flags = in_array('ADMIN_OWNER', $_POST['web_flags']) ? array('ADMIN_OWNER') : $_POST['web_flags'];
        }
        
        GroupsWriter::add($_POST['type'], $_POST['name'], $flags, isset($_POST['immunity']) && is_numeric($_POST['immunity']) ? $_POST['immunity'] : 0, $_POST['overrides']);
        break;
      case 'import':
        GroupsWriter::import($_FILES['file']['name'], $_FILES['file']['tmp_name']);
    }
    
    Util::redirect();
  }
  
  $groups_reader       = new GroupsReader();
  
  $groups_reader->type = SERVER_ADMIN_GROUPS;
  $server_admin_groups = $groups_reader->executeCached(ONE_MINUTE * 5);
  
  $groups_reader->type = WEB_ADMIN_GROUPS;
  $web_admin_groups    = $groups_reader->executeCached(ONE_MINUTE * 5);
  
  foreach($server_admin_groups as &$group)
  {
    $permission_root                      = strpos($group['flags'],                     SM_ROOT)        !== false;
    $group['permission_reservation']      = $permission_root || strpos($group['flags'], SM_RESERVATION) !== false;
    $group['permission_generic']          = $permission_root || strpos($group['flags'], SM_GENERIC)     !== false;
    $group['permission_kick']             = $permission_root || strpos($group['flags'], SM_KICK)        !== false;
    $group['permission_ban']              = $permission_root || strpos($group['flags'], SM_BAN)         !== false;
    $group['permission_unban']            = $permission_root || strpos($group['flags'], SM_UNBAN)       !== false;
    $group['permission_slay']             = $permission_root || strpos($group['flags'], SM_SLAY)        !== false;
    $group['permission_changemap']        = $permission_root || strpos($group['flags'], SM_CHANGEMAP)   !== false;
    $group['permission_cvar']             = $permission_root || strpos($group['flags'], SM_CVAR)        !== false;
    $group['permission_config']           = $permission_root || strpos($group['flags'], SM_CONFIG)      !== false;
    $group['permission_chat']             = $permission_root || strpos($group['flags'], SM_CHAT)        !== false;
    $group['permission_vote']             = $permission_root || strpos($group['flags'], SM_VOTE)        !== false;
    $group['permission_password']         = $permission_root || strpos($group['flags'], SM_PASSWORD)    !== false;
    $group['permission_rcon']             = $permission_root || strpos($group['flags'], SM_RCON)        !== false;
    $group['permission_cheats']           = $permission_root || strpos($group['flags'], SM_CHEATS)      !== false;
    $group['permission_custom1']          = $permission_root || strpos($group['flags'], SM_CUSTOM1)     !== false;
    $group['permission_custom2']          = $permission_root || strpos($group['flags'], SM_CUSTOM2)     !== false;
    $group['permission_custom3']          = $permission_root || strpos($group['flags'], SM_CUSTOM3)     !== false;
    $group['permission_custom4']          = $permission_root || strpos($group['flags'], SM_CUSTOM4)     !== false;
    $group['permission_custom5']          = $permission_root || strpos($group['flags'], SM_CUSTOM5)     !== false;
    $group['permission_custom6']          = $permission_root || strpos($group['flags'], SM_CUSTOM6)     !== false;
    $group['permission_root']             = $permission_root;
  }
  
  foreach($web_admin_groups as &$group)
  {
    $permission_owner                     = in_array('ADMIN_OWNER',                                 $group['flags']);
    $group['permission_add_admins']       = $permission_owner || in_array('ADMIN_ADD_ADMINS',       $group['flags']);
    $group['permission_delete_admins']    = $permission_owner || in_array('ADMIN_DELETE_ADMINS',    $group['flags']);
    $group['permission_edit_admins']      = $permission_owner || in_array('ADMIN_EDIT_ADMINS',      $group['flags']);
    $group['permission_import_admins']    = $permission_owner || in_array('ADMIN_IMPORT_ADMINS',    $group['flags']);
    $group['permission_list_admins']      = $permission_owner || in_array('ADMIN_LIST_ADMINS',      $group['flags']);
    $group['permission_add_groups']       = $permission_owner || in_array('ADMIN_ADD_GROUPS',       $group['flags']);
    $group['permission_delete_groups']    = $permission_owner || in_array('ADMIN_DELETE_GROUPS',    $group['flags']);
    $group['permission_edit_groups']      = $permission_owner || in_array('ADMIN_EDIT_GROUPS',      $group['flags']);
    $group['permission_import_groups']    = $permission_owner || in_array('ADMIN_IMPORT_GROUPS',    $group['flags']);
    $group['permission_list_groups']      = $permission_owner || in_array('ADMIN_LIST_GROUPS',      $group['flags']);
    $group['permission_add_mods']         = $permission_owner || in_array('ADMIN_ADD_MODS',         $group['flags']);
    $group['permission_delete_mods']      = $permission_owner || in_array('ADMIN_DELETE_MODS',      $group['flags']);
    $group['permission_edit_mods']        = $permission_owner || in_array('ADMIN_EDIT_MODS',        $group['flags']);
    $group['permission_list_mods']        = $permission_owner || in_array('ADMIN_LIST_MODS',        $group['flags']);
    $group['permission_add_servers']      = $permission_owner || in_array('ADMIN_ADD_SERVERS',      $group['flags']);
    $group['permission_delete_servers']   = $permission_owner || in_array('ADMIN_DELETE_SERVERS',   $group['flags']);
    $group['permission_edit_servers']     = $permission_owner || in_array('ADMIN_EDIT_SERVERS',     $group['flags']);
    $group['permission_list_servers']     = $permission_owner || in_array('ADMIN_LIST_SERVERS',     $group['flags']);
    $group['permission_import_servers']   = $permission_owner || in_array('ADMIN_IMPORT_SERVERS',   $group['flags']);
    $group['permission_add_bans']         = $permission_owner || in_array('ADMIN_ADD_BANS',         $group['flags']);
    $group['permission_delete_bans']      = $permission_owner || in_array('ADMIN_DELETE_BANS',      $group['flags']);
    $group['permission_edit_all_bans']    = $permission_owner || in_array('ADMIN_EDIT_ALL_BANS',    $group['flags']);
    $group['permission_edit_group_bans']  = $permission_owner || in_array('ADMIN_EDIT_GROUP_BANS',  $group['flags']);
    $group['permission_edit_own_bans']    = $permission_owner || in_array('ADMIN_EDIT_OWN_BANS',    $group['flags']);
    $group['permission_import_bans']      = $permission_owner || in_array('ADMIN_IMPORT_BANS',      $group['flags']);
    $group['permission_unban_all_bans']   = $permission_owner || in_array('ADMIN_UNBAN_ALL_BANS',   $group['flags']);
    $group['permission_unban_group_bans'] = $permission_owner || in_array('ADMIN_UNBAN_GROUP_BANS', $group['flags']);
    $group['permission_unban_own_bans']   = $permission_owner || in_array('ADMIN_UNBAN_OWN_BANS',   $group['flags']);
    $group['permission_ban_protests']     = $permission_owner || in_array('ADMIN_BAN_PROTESTS',     $group['flags']);
    $group['permission_ban_submissions']  = $permission_owner || in_array('ADMIN_BAN_SUBMISSIONS',  $group['flags']);
    $group['permission_notify_prot']      = $permission_owner || in_array('ADMIN_NOTIFY_PROT',      $group['flags']);
    $group['permission_notify_sub']       = $permission_owner || in_array('ADMIN_NOTIFY_SUB',       $group['flags']);
    $group['permission_settings']         = $permission_owner || in_array('ADMIN_SETTINGS',         $group['flags']);
    $group['permission_owner']            = $permission_owner;
  }
  
  $page->assign('permission_add_groups',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_GROUPS')));
  $page->assign('permission_delete_groups', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_GROUPS')));
  $page->assign('permission_edit_groups',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_GROUPS')));
  $page->assign('permission_import_groups', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_GROUPS')));
  $page->assign('permission_list_groups',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_GROUPS')));
  $page->assign('server_admin_group_count', count($server_admin_groups));
  $page->assign('server_admin_groups',      $server_admin_groups);
  $page->assign('web_admin_group_count',    count($web_admin_groups));
  $page->assign('web_admin_groups',         $web_admin_groups);
  $page->display('page_admin_groups');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>