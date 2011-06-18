<?php
class GroupsController extends BaseController
{
  protected function _title()
  {
    return ucwords($this->_registry->user->language->groups);
  }
  
  
  public function index() {}
  
  
  public function edit()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'EDIT_GROUPS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        // Parse flags and overrides depending on group type
        switch($_POST['type'])
        {
          case SERVER_GROUPS:
            $group            = SB_API::getServerGroup($_POST['id']);
            // If flag array contains root flag, only pass root flag, otherwise create flag string
            $group->flags     = Util::in_array(SM_ROOT, $_POST['srv_flags']) ? SM_ROOT        : implode($_POST['srv_flags']);
            $group->overrides = array();
            
            foreach($_POST['override_name'] as $id => $name)
            {
              if(empty($name))
                continue;
              
              $group->overrides[] = array(
                'name'   => $name,
                'access' => $_POST['override_access'][$id],
                'type'   => $_POST['override_type'][$id]
              );
            }
            break;
          case WEB_GROUPS:
            $group        = SB_API::getServerGroup($_POST['id']);
            // If flag array contains owner flag, only pass owner flag, otherwise pass entire flag array
            $group->flags = Util::in_array('OWNER', $_POST['web_flags']) ? array('OWNER') : $_POST['web_flags'];
            
            break;
          default:
            throw new SBException($language->invalid_type);
        }
        
        $group->save();
        
        exit(json_encode(array(
          'redirect' => new SBUri('admin', 'groups')
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    switch($uri->type)
    {
      case SERVER_GROUPS:
        $group           = SB_API::getServerGroup($uri->id);
        $flags           = $group['flags'];
        $permission_root = strpos($flags, SM_ROOT) !== false;
        
        $group->permission_reservation = $permission_root || strpos($flags, SM_RESERVATION) !== false;
        $group->permission_generic     = $permission_root || strpos($flags, SM_GENERIC)     !== false;
        $group->permission_kick        = $permission_root || strpos($flags, SM_KICK)        !== false;
        $group->permission_ban         = $permission_root || strpos($flags, SM_BAN)         !== false;
        $group->permission_unban       = $permission_root || strpos($flags, SM_UNBAN)       !== false;
        $group->permission_slay        = $permission_root || strpos($flags, SM_SLAY)        !== false;
        $group->permission_changemap   = $permission_root || strpos($flags, SM_CHANGEMAP)   !== false;
        $group->permission_cvar        = $permission_root || strpos($flags, SM_CVAR)        !== false;
        $group->permission_config      = $permission_root || strpos($flags, SM_CONFIG)      !== false;
        $group->permission_chat        = $permission_root || strpos($flags, SM_CHAT)        !== false;
        $group->permission_vote        = $permission_root || strpos($flags, SM_VOTE)        !== false;
        $group->permission_password    = $permission_root || strpos($flags, SM_PASSWORD)    !== false;
        $group->permission_rcon        = $permission_root || strpos($flags, SM_RCON)        !== false;
        $group->permission_cheats      = $permission_root || strpos($flags, SM_CHEATS)      !== false;
        $group->permission_custom1     = $permission_root || strpos($flags, SM_CUSTOM1)     !== false;
        $group->permission_custom2     = $permission_root || strpos($flags, SM_CUSTOM2)     !== false;
        $group->permission_custom3     = $permission_root || strpos($flags, SM_CUSTOM3)     !== false;
        $group->permission_custom4     = $permission_root || strpos($flags, SM_CUSTOM4)     !== false;
        $group->permission_custom5     = $permission_root || strpos($flags, SM_CUSTOM5)     !== false;
        $group->permission_custom6     = $permission_root || strpos($flags, SM_CUSTOM6)     !== false;
        $group->permission_root        = $permission_root;
        break;
      case WEB_GROUPS:
        $group                       = SB_API::getWebGroup($uri->id);
        $flags                       = $group['flags'];
        $permission_owner            = Util::in_array('OWNER',                                 $flags);
        $permission_add_admins       = $permission_owner || Util::in_array('ADD_ADMINS',       $flags);
        $permission_delete_admins    = $permission_owner || Util::in_array('DELETE_ADMINS',    $flags);
        $permission_edit_admins      = $permission_owner || Util::in_array('EDIT_ADMINS',      $flags);
        $permission_import_admins    = $permission_owner || Util::in_array('IMPORT_ADMINS',    $flags);
        $permission_list_admins      = $permission_owner || Util::in_array('LIST_ADMINS',      $flags);
        $permission_add_groups       = $permission_owner || Util::in_array('ADD_GROUPS',       $flags);
        $permission_delete_groups    = $permission_owner || Util::in_array('DELETE_GROUPS',    $flags);
        $permission_edit_groups      = $permission_owner || Util::in_array('EDIT_GROUPS',      $flags);
        $permission_import_groups    = $permission_owner || Util::in_array('IMPORT_GROUPS',    $flags);
        $permission_list_groups      = $permission_owner || Util::in_array('LIST_GROUPS',      $flags);
        $permission_add_games        = $permission_owner || Util::in_array('ADD_GAMES',        $flags);
        $permission_delete_games     = $permission_owner || Util::in_array('DELETE_GAMES',     $flags);
        $permission_edit_games       = $permission_owner || Util::in_array('EDIT_GAMES',       $flags);
        $permission_list_games       = $permission_owner || Util::in_array('LIST_GAMES',       $flags);
        $permission_add_servers      = $permission_owner || Util::in_array('ADD_SERVERS',      $flags);
        $permission_delete_servers   = $permission_owner || Util::in_array('DELETE_SERVERS',   $flags);
        $permission_edit_servers     = $permission_owner || Util::in_array('EDIT_SERVERS',     $flags);
        $permission_import_servers   = $permission_owner || Util::in_array('IMPORT_SERVERS',   $flags);
        $permission_list_servers     = $permission_owner || Util::in_array('LIST_SERVERS',     $flags);
        $permission_add_bans         = $permission_owner || Util::in_array('ADD_BANS',         $flags);
        $permission_delete_bans      = $permission_owner || Util::in_array('DELETE_BANS',      $flags);
        $permission_edit_all_bans    = $permission_owner || Util::in_array('EDIT_ALL_BANS',    $flags);
        $permission_edit_group_bans  = $permission_owner || Util::in_array('EDIT_GROUP_BANS',  $flags);
        $permission_edit_own_bans    = $permission_owner || Util::in_array('EDIT_OWN_BANS',    $flags);
        $permission_import_bans      = $permission_owner || Util::in_array('IMPORT_BANS',      $flags);
        $permission_unban_all_bans   = $permission_owner || Util::in_array('UNBAN_ALL_BANS',   $flags);
        $permission_unban_group_bans = $permission_owner || Util::in_array('UNBAN_GROUP_BANS', $flags);
        $permission_unban_own_bans   = $permission_owner || Util::in_array('UNBAN_OWN_BANS',   $flags);
        $permission_ban_protests     = $permission_owner || Util::in_array('BAN_PROTESTS',     $flags);
        $permission_ban_submissions  = $permission_owner || Util::in_array('BAN_SUBMISSIONS',  $flags);
        $permission_notify_prot      = $permission_owner || Util::in_array('NOTIFY_PROT',      $flags);
        $permission_notify_sub       = $permission_owner || Util::in_array('NOTIFY_SUB',       $flags);
        $permission_settings         = $permission_owner || Util::in_array('SETTINGS',         $flags);
        
        $group->permission_add_admins       = $permission_add_admins;
        $group->permission_delete_admins    = $permission_delete_admins;
        $group->permission_edit_admins      = $permission_edit_admins;
        $group->permission_import_admins    = $permission_import_admins;
        $group->permission_list_admins      = $permission_list_admins;
        $group->permission_add_groups       = $permission_add_groups;
        $group->permission_delete_groups    = $permission_delete_groups;
        $group->permission_edit_groups      = $permission_edit_groups;
        $group->permission_import_groups    = $permission_import_groups;
        $group->permission_list_groups      = $permission_list_groups;
        $group->permission_add_games         = $permission_add_games;
        $group->permission_delete_games      = $permission_delete_games;
        $group->permission_edit_games        = $permission_edit_games;
        $group->permission_list_games        = $permission_list_games;
        $group->permission_add_servers      = $permission_add_servers;
        $group->permission_delete_servers   = $permission_delete_servers;
        $group->permission_edit_servers     = $permission_edit_servers;
        $group->permission_import_servers   = $permission_import_servers;
        $group->permission_list_servers     = $permission_list_servers;
        $group->permission_add_bans         = $permission_add_bans;
        $group->permission_delete_bans      = $permission_delete_bans;
        $group->permission_edit_all_bans    = $permission_edit_all_bans;
        $group->permission_edit_group_bans  = $permission_edit_group_bans;
        $group->permission_edit_own_bans    = $permission_edit_own_bans;
        $group->permission_import_bans      = $permission_import_bans;
        $group->permission_unban_all_bans   = $permission_unban_all_bans;
        $group->permission_unban_group_bans = $permission_unban_group_bans;
        $group->permission_unban_own_bans   = $permission_unban_own_bans;
        $group->permission_ban_protests     = $permission_ban_protests;
        $group->permission_ban_submissions  = $permission_ban_submissions;
        $group->permission_notify_prot      = $permission_notify_prot;
        $group->permission_notify_sub       = $permission_notify_sub;
        $group->permission_settings         = $permission_settings;
        $group->permission_admins           = $permission_add_admins     && $permission_delete_admins    && $permission_edit_admins    && $permission_import_admins   && $permission_list_admins;
        $group->permission_bans             = $permission_add_bans       && $permission_delete_bans      && $permission_edit_all_bans  && $permission_edit_group_bans && $permission_edit_own_bans && $permission_import_bans &&
                                              $permission_unban_all_bans && $permission_unban_group_bans && $permission_unban_own_bans && $permission_ban_protests    && $permission_ban_submissions;
        $group->permission_groups           = $permission_add_groups     && $permission_delete_groups    && $permission_edit_groups    && $permission_import_groups   && $permission_list_groups;
        $group->permission_games             = $permission_add_games       && $permission_delete_games      && $permission_edit_games      && $permission_list_games;
        $group->permission_notify           = $permission_notify_prot    && $permission_notify_sub;
        $group->permission_servers          = $permission_add_servers    && $permission_delete_servers   && $permission_edit_servers   && $permission_import_servers  && $permission_list_servers;
        $group->permission_owner            = $permission_owner;
        break;
      default:
        throw new SBException($language->invalid_type);
    }
    
    $template->action_title = ucwords($language->edit_group);
    $template->group        = $group;
    $template->display('admin_groups_edit');
  }
}