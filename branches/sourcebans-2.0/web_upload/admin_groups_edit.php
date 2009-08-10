<?php
require_once 'init.php';
require_once READERS_DIR . 'groups.php';
require_once WRITERS_DIR . 'groups.php';

$userbank = Env::get('userbank');
$phrases  = Env::get('phrases');
$page     = new Page(ucwords($phrases['edit_group']));

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_GROUPS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
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
    
    GroupsWriter::edit($_POST['id'], $_POST['type'], $_POST['name'], $flags, isset($_POST['immunity']) && is_numeric($_POST['immunity']) ? $_POST['immunity'] : 0, $_POST['overrides']);
    
    Util::redirect();
  }
  
  if(!isset($_GET['type']) || !in_array($_GET['type'], array(SERVER_ADMIN_GROUPS, WEB_ADMIN_GROUPS)))
    throw new Exception('Invalid group type specified.');
  
  $groups_reader       = new GroupsReader();
  $groups_reader->type = $_GET['type'];
  $groups              = $groups_reader->executeCached(ONE_MINUTE * 5);
  
  if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($groups[$_GET['id']]))
    throw new Exception('Invalid ID specified.');
  
  $id                  = $_GET['id'];
  
  $page->assign('group_name', $groups[$id]['name']);
  
  switch($groups_reader->type)
  {
    case SERVER_ADMIN_GROUPS:
      $flags           = $groups[$id]['flags'];
      $permission_root = strpos($flags, SM_ROOT) !== false;
      
      $page->assign('group_immunity',               $groups[$id]['immunity']);
      $page->assign('group_overrides',              $groups[$id]['overrides']);
      $page->assign('group_permission_reservation', $permission_root || strpos($flags, SM_RESERVATION) !== false);
      $page->assign('group_permission_generic',     $permission_root || strpos($flags, SM_GENERIC)     !== false);
      $page->assign('group_permission_kick',        $permission_root || strpos($flags, SM_KICK)        !== false);
      $page->assign('group_permission_ban',         $permission_root || strpos($flags, SM_BAN)         !== false);
      $page->assign('group_permission_unban',       $permission_root || strpos($flags, SM_UNBAN)       !== false);
      $page->assign('group_permission_slay',        $permission_root || strpos($flags, SM_SLAY)        !== false);
      $page->assign('group_permission_changemap',   $permission_root || strpos($flags, SM_CHANGEMAP)   !== false);
      $page->assign('group_permission_cvar',        $permission_root || strpos($flags, SM_CVAR)        !== false);
      $page->assign('group_permission_config',      $permission_root || strpos($flags, SM_CONFIG)      !== false);
      $page->assign('group_permission_chat',        $permission_root || strpos($flags, SM_CHAT)        !== false);
      $page->assign('group_permission_vote',        $permission_root || strpos($flags, SM_VOTE)        !== false);
      $page->assign('group_permission_password',    $permission_root || strpos($flags, SM_PASSWORD)    !== false);
      $page->assign('group_permission_rcon',        $permission_root || strpos($flags, SM_RCON)        !== false);
      $page->assign('group_permission_cheats',      $permission_root || strpos($flags, SM_CHEATS)      !== false);
      $page->assign('group_permission_custom1',     $permission_root || strpos($flags, SM_CUSTOM1)     !== false);
      $page->assign('group_permission_custom2',     $permission_root || strpos($flags, SM_CUSTOM2)     !== false);
      $page->assign('group_permission_custom3',     $permission_root || strpos($flags, SM_CUSTOM3)     !== false);
      $page->assign('group_permission_custom4',     $permission_root || strpos($flags, SM_CUSTOM4)     !== false);
      $page->assign('group_permission_custom5',     $permission_root || strpos($flags, SM_CUSTOM5)     !== false);
      $page->assign('group_permission_custom6',     $permission_root || strpos($flags, SM_CUSTOM6)     !== false);
      $page->assign('group_permission_root',        $permission_root);
      
      break;
    case WEB_ADMIN_GROUPS:
      $flags                       = $groups[$id]['flags'];
      $permission_owner            = in_array('ADMIN_OWNER',                                 $flags);
      $permission_add_admins       = $permission_owner || in_array('ADMIN_ADD_ADMINS',       $flags);
      $permission_delete_admins    = $permission_owner || in_array('ADMIN_DELETE_ADMINS',    $flags);
      $permission_edit_admins      = $permission_owner || in_array('ADMIN_EDIT_ADMINS',      $flags);
      $permission_import_admins    = $permission_owner || in_array('ADMIN_IMPORT_ADMINS',    $flags);
      $permission_list_admins      = $permission_owner || in_array('ADMIN_LIST_ADMINS',      $flags);
      $permission_add_groups       = $permission_owner || in_array('ADMIN_ADD_GROUPS',       $flags);
      $permission_delete_groups    = $permission_owner || in_array('ADMIN_DELETE_GROUPS',    $flags);
      $permission_edit_groups      = $permission_owner || in_array('ADMIN_EDIT_GROUPS',      $flags);
      $permission_import_groups    = $permission_owner || in_array('ADMIN_IMPORT_GROUPS',    $flags);
      $permission_list_groups      = $permission_owner || in_array('ADMIN_LIST_GROUPS',      $flags);
      $permission_add_mods         = $permission_owner || in_array('ADMIN_ADD_MODS',         $flags);
      $permission_delete_mods      = $permission_owner || in_array('ADMIN_DELETE_MODS',      $flags);
      $permission_edit_mods        = $permission_owner || in_array('ADMIN_EDIT_MODS',        $flags);
      $permission_list_mods        = $permission_owner || in_array('ADMIN_LIST_MODS',        $flags);
      $permission_add_servers      = $permission_owner || in_array('ADMIN_ADD_SERVERS',      $flags);
      $permission_delete_servers   = $permission_owner || in_array('ADMIN_DELETE_SERVERS',   $flags);
      $permission_edit_servers     = $permission_owner || in_array('ADMIN_EDIT_SERVERS',     $flags);
      $permission_import_servers   = $permission_owner || in_array('ADMIN_IMPORT_SERVERS',   $flags);
      $permission_list_servers     = $permission_owner || in_array('ADMIN_LIST_SERVERS',     $flags);
      $permission_add_bans         = $permission_owner || in_array('ADMIN_ADD_BANS',         $flags);
      $permission_delete_bans      = $permission_owner || in_array('ADMIN_DELETE_BANS',      $flags);
      $permission_edit_all_bans    = $permission_owner || in_array('ADMIN_EDIT_ALL_BANS',    $flags);
      $permission_edit_group_bans  = $permission_owner || in_array('ADMIN_EDIT_GROUP_BANS',  $flags);
      $permission_edit_own_bans    = $permission_owner || in_array('ADMIN_EDIT_OWN_BANS',    $flags);
      $permission_import_bans      = $permission_owner || in_array('ADMIN_IMPORT_BANS',      $flags);
      $permission_unban_all_bans   = $permission_owner || in_array('ADMIN_UNBAN_ALL_BANS',   $flags);
      $permission_unban_group_bans = $permission_owner || in_array('ADMIN_UNBAN_GROUP_BANS', $flags);
      $permission_unban_own_bans   = $permission_owner || in_array('ADMIN_UNBAN_OWN_BANS',   $flags);
      $permission_ban_protests     = $permission_owner || in_array('ADMIN_BAN_PROTESTS',     $flags);
      $permission_ban_submissions  = $permission_owner || in_array('ADMIN_BAN_SUBMISSIONS',  $flags);
      $permission_notify_prot      = $permission_owner || in_array('ADMIN_NOTIFY_PROT',      $flags);
      $permission_notify_sub       = $permission_owner || in_array('ADMIN_NOTIFY_SUB',       $flags);
      $permission_settings         = $permission_owner || in_array('ADMIN_SETTINGS',         $flags);
      
      $page->assign('group_permission_add_admins',       $permission_add_admins);
      $page->assign('group_permission_delete_admins',    $permission_delete_admins);
      $page->assign('group_permission_edit_admins',      $permission_edit_admins);
      $page->assign('group_permission_import_admins',    $permission_import_admins);
      $page->assign('group_permission_list_admins',      $permission_list_admins);
      $page->assign('group_permission_add_groups',       $permission_add_groups);
      $page->assign('group_permission_delete_groups',    $permission_delete_groups);
      $page->assign('group_permission_edit_groups',      $permission_edit_groups);
      $page->assign('group_permission_import_groups',    $permission_import_groups);
      $page->assign('group_permission_list_groups',      $permission_list_groups);
      $page->assign('group_permission_add_mods',         $permission_add_mods);
      $page->assign('group_permission_delete_mods',      $permission_delete_mods);
      $page->assign('group_permission_edit_mods',        $permission_edit_mods);
      $page->assign('group_permission_list_mods',        $permission_list_mods);
      $page->assign('group_permission_add_servers',      $permission_add_servers);
      $page->assign('group_permission_delete_servers',   $permission_delete_servers);
      $page->assign('group_permission_edit_servers',     $permission_edit_servers);
      $page->assign('group_permission_import_servers',   $permission_import_servers);
      $page->assign('group_permission_list_servers',     $permission_list_servers);
      $page->assign('group_permission_add_bans',         $permission_add_bans);
      $page->assign('group_permission_delete_bans',      $permission_delete_bans);
      $page->assign('group_permission_edit_all_bans',    $permission_edit_all_bans);
      $page->assign('group_permission_edit_group_bans',  $permission_edit_group_bans);
      $page->assign('group_permission_edit_own_bans',    $permission_edit_own_bans);
      $page->assign('group_permission_import_bans',      $permission_import_bans);
      $page->assign('group_permission_unban_all_bans',   $permission_unban_all_bans);
      $page->assign('group_permission_unban_group_bans', $permission_unban_group_bans);
      $page->assign('group_permission_unban_own_bans',   $permission_unban_own_bans);
      $page->assign('group_permission_ban_protests',     $permission_ban_protests);
      $page->assign('group_permission_ban_submissions',  $permission_ban_submissions);
      $page->assign('group_permission_notify_prot',      $permission_notify_prot);
      $page->assign('group_permission_notify_sub',       $permission_notify_sub);
      $page->assign('group_permission_settings',         $permission_settings);
      $page->assign('group_permission_admins',           $permission_add_admins     && $permission_delete_admins    && $permission_edit_admins    && $permission_import_admins   && $permission_list_admins);
      $page->assign('group_permission_bans',             $permission_add_bans       && $permission_delete_bans      && $permission_edit_all_bans  && $permission_edit_group_bans && $permission_edit_own_bans && $permission_import_bans &&
                                                         $permission_unban_all_bans && $permission_unban_group_bans && $permission_unban_own_bans && $permission_ban_protests    && $permission_ban_submissions);
      $page->assign('group_permission_groups',           $permission_add_groups     && $permission_delete_groups    && $permission_edit_groups    && $permission_import_groups   && $permission_list_groups);
      $page->assign('group_permission_mods',             $permission_add_mods       && $permission_delete_mods      && $permission_edit_mods      && $permission_list_mods);
      $page->assign('group_permission_notify',           $permission_notify_prot    && $permission_notify_sub);
      $page->assign('group_permission_servers',          $permission_add_servers    && $permission_delete_servers   && $permission_edit_servers   && $permission_import_servers  && $permission_list_servers);
      $page->assign('group_permission_owner',            $permission_owner);
  }
  
  $page->display('page_admin_groups_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>