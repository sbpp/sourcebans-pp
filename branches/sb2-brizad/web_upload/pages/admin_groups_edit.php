<?php
require_once 'api.php';

$phrases  = SBConfig::getEnv('phrases');
$userbank = SBConfig::getEnv('userbank');
$page     = new Page(ucwords($phrases['edit_group']), !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'EDIT_GROUPS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      // Parse flags and overrides depending on group type
      switch($_POST['type'])
      {
        case SERVER_GROUPS:
          // If flag array contains root flag, only pass root flag, otherwise create flag string
          $flags     = in_array(SM_ROOT, $_POST['srv_flags']) ? SM_ROOT        : implode($_POST['srv_flags']);
          $overrides = array();
          
          foreach($_POST['override_name'] as $id => $name)
          {
            if(!empty($name))
              $overrides[] = array('name'   => $name,
                                   'access' => $_POST['override_access'][$id],
                                   'type'   => $_POST['override_type'][$id]);
          }
          
          break;
        case WEB_GROUPS:
          // If flag array contains owner flag, only pass owner flag, otherwise pass entire flag array
          $flags     = in_array('OWNER', $_POST['web_flags']) ? array('OWNER') : $_POST['web_flags'];
          $overrides = null;
          
          break;
        default:
          throw new Exception($phrases['invalid_type']);
      }
      
      SB_API::editGroup($_POST['id'], $_POST['type'], $_POST['name'], $flags, isset($_POST['immunity']) && is_numeric($_POST['immunity']) ? $_POST['immunity'] : 0, $overrides);
      
      exit(json_encode(array(
        'redirect' => Util::buildUrl(array(
          '_' => 'admin_groups.php'
        ))
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $group = SB_API::getGroup($_GET['type'], $_GET['id']);
  
  switch($_GET['type'])
  {
    case SERVER_GROUPS:
      $flags           = $group['flags'];
      $permission_root = strpos($flags, SM_ROOT) !== false;
      
      $page->assign('group_immunity',               $group['immunity']);
      $page->assign('group_overrides',              $group['overrides']);
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
    case WEB_GROUPS:
      $flags                       = $group['flags'];
      $permission_owner            = in_array('OWNER',                                 $flags);
      $permission_add_admins       = $permission_owner || in_array('ADD_ADMINS',       $flags);
      $permission_delete_admins    = $permission_owner || in_array('DELETE_ADMINS',    $flags);
      $permission_edit_admins      = $permission_owner || in_array('EDIT_ADMINS',      $flags);
      $permission_import_admins    = $permission_owner || in_array('IMPORT_ADMINS',    $flags);
      $permission_list_admins      = $permission_owner || in_array('LIST_ADMINS',      $flags);
      $permission_add_groups       = $permission_owner || in_array('ADD_GROUPS',       $flags);
      $permission_delete_groups    = $permission_owner || in_array('DELETE_GROUPS',    $flags);
      $permission_edit_groups      = $permission_owner || in_array('EDIT_GROUPS',      $flags);
      $permission_import_groups    = $permission_owner || in_array('IMPORT_GROUPS',    $flags);
      $permission_list_groups      = $permission_owner || in_array('LIST_GROUPS',      $flags);
      $permission_add_mods         = $permission_owner || in_array('ADD_MODS',         $flags);
      $permission_delete_mods      = $permission_owner || in_array('DELETE_MODS',      $flags);
      $permission_edit_mods        = $permission_owner || in_array('EDIT_MODS',        $flags);
      $permission_list_mods        = $permission_owner || in_array('LIST_MODS',        $flags);
      $permission_add_servers      = $permission_owner || in_array('ADD_SERVERS',      $flags);
      $permission_delete_servers   = $permission_owner || in_array('DELETE_SERVERS',   $flags);
      $permission_edit_servers     = $permission_owner || in_array('EDIT_SERVERS',     $flags);
      $permission_import_servers   = $permission_owner || in_array('IMPORT_SERVERS',   $flags);
      $permission_list_servers     = $permission_owner || in_array('LIST_SERVERS',     $flags);
      $permission_add_bans         = $permission_owner || in_array('ADD_BANS',         $flags);
      $permission_delete_bans      = $permission_owner || in_array('DELETE_BANS',      $flags);
      $permission_edit_all_bans    = $permission_owner || in_array('EDIT_ALL_BANS',    $flags);
      $permission_edit_group_bans  = $permission_owner || in_array('EDIT_GROUP_BANS',  $flags);
      $permission_edit_own_bans    = $permission_owner || in_array('EDIT_OWN_BANS',    $flags);
      $permission_import_bans      = $permission_owner || in_array('IMPORT_BANS',      $flags);
      $permission_unban_all_bans   = $permission_owner || in_array('UNBAN_ALL_BANS',   $flags);
      $permission_unban_group_bans = $permission_owner || in_array('UNBAN_GROUP_BANS', $flags);
      $permission_unban_own_bans   = $permission_owner || in_array('UNBAN_OWN_BANS',   $flags);
      $permission_ban_protests     = $permission_owner || in_array('BAN_PROTESTS',     $flags);
      $permission_ban_submissions  = $permission_owner || in_array('BAN_SUBMISSIONS',  $flags);
      $permission_notify_prot      = $permission_owner || in_array('NOTIFY_PROT',      $flags);
      $permission_notify_sub       = $permission_owner || in_array('NOTIFY_SUB',       $flags);
      $permission_settings         = $permission_owner || in_array('SETTINGS',         $flags);
      
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
      
      break;
    default:
      throw new Exception($phrases['invalid_type']);
  }
  
  $page->assign('group_name', $group['name']);
  $page->display('page_admin_groups_edit');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>