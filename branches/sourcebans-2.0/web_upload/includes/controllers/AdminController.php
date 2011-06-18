<?php
class AdminController extends BaseController
{
  protected function _title()
  {
    return $this->_registry->user->language->administration;
  }
  
  
  public function index()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $user     = $this->_registry->user;
    
    if(!$user->is_admin())
      throw new SBException($language->access_denied);
    
    $admins      = $this->_registry->admins;
    $bans        = $this->_registry->bans;
    $blocks      = $this->_registry->blocks;
    $protests    = $this->_registry->protests;
    $servers     = $this->_registry->servers;
    $submissions = $this->_registry->submissions;
    //$demosize    = Util::getDirectorySize(self::$_registry->site_dir . 'demos');
    
    //$template->demosize                   = Util::formatSize($demosize['size']);
    $template->total_admins               = count($admins);
    //$template->total_archived_protests    = count($archived_protests);
    //$template->total_archived_submissions = count($archived_submissions);
    $template->total_bans                 = count($bans);
    $template->total_blocks               = count($blocks);
    $template->total_protests             = count($protests);
    $template->total_servers              = count($servers);
    $template->total_submissions          = count($submissions);
    $template->display('admin');
  }
  
  
  public function admins()
  {
    $settings = $this->_registry->settings;
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'ADD_ADMINS', 'DELETE_ADMINS', 'EDIT_ADMINS', 'LIST_ADMINS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        switch($_POST['action'])
        {
          case 'add':
            if(!$user->hasAccess(array('OWNER', 'ADD_ADMINS')))
              throw new SBException($language->access_denied);
            if($_POST['password'] != $_POST['password_confirm'])
              throw new SBException($language->passwords_do_not_match);
            
            $admin             = SB_API::createAdmin();
            $admin->auth       = $_POST['auth'];
            $admin->email      = $_POST['email'];
            $admin->group_id   = $_POST['web_group'];
            $admin->identity   = ($_POST['auth'] == $this->_registry->steam_auth_type ? strtoupper($_POST['identity']) : $_POST['identity']);
            $admin->name       = $_POST['name'];
            $admin->password   = $this->_registry->admins->encrypt_password($_POST['password']);
            
            if(isset($_POST['srv_password']))
            {
              $admin->srv_password = $_POST['password'];
            }
            
            $admin->setServerGroups($_POST['srv_groups']);
            $admin->save();
            break;
          case 'import':
            if(!$user->hasAccess(array('OWNER', 'IMPORT_ADMINS')))
              throw new SBException($language->access_denied);
            
            SB_API::importAdmins($_FILES['file']['name'], $_FILES['file']['tmp_name']);
            break;
          default:
            throw new SBException($language->invalid_action);
        }
        
        exit(json_encode(array(
          'redirect' => $uri
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $limit        = $settings->items_per_page;
    $page         = isset($uri->page) && is_numeric($uri->page) && $uri->page > 1 ? $uri->page : 1;
    
    $order        = isset($uri->order) ? $uri->order : 'asc';
    $sort         = isset($uri->sort)  ? $uri->sort  : 'name';
    
    /*$actions      = SB_API::getActions($limit, $pagenr, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC,
                                       isset($uri->search) ? $uri->search : null, isset($uri->type) ? $uri->type : null);
    $admins       = SB_API::getAdmins($limit,  $pagenr, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC,
                                      isset($uri->search) ? $uri->search : null, isset($uri->type) ? $uri->type : null);*/
    $actions      = $this->_registry->actions;
    $admins       = $this->_registry->admins;
    
    $count        = count($admins);
    $admins_start = ($page - 1)   * $limit;
    $admins_end   = $admins_start + $limit;
    $pages        = ceil($count   / $limit);
    if($admins_end > $count)
      $admins_end = $count;
    
    foreach($admins as $admin)
    {
      /*$admin->permission_reservation      = $admin->hasAccess(SM_ROOT . SM_RESERVATION,           $admin->id);
      $admin->permission_generic          = $admin->hasAccess(SM_ROOT . SM_GENERIC,               $admin->id);
      $admin->permission_kick             = $admin->hasAccess(SM_ROOT . SM_KICK,                  $admin->id);
      $admin->permission_ban              = $admin->hasAccess(SM_ROOT . SM_BAN,                   $admin->id);
      $admin->permission_unban            = $admin->hasAccess(SM_ROOT . SM_UNBAN,                 $admin->id);
      $admin->permission_slay             = $admin->hasAccess(SM_ROOT . SM_SLAY,                  $admin->id);
      $admin->permission_changemap        = $admin->hasAccess(SM_ROOT . SM_CHANGEMAP,             $admin->id);
      $admin->permission_cvar             = $admin->hasAccess(SM_ROOT . SM_CVAR,                  $admin->id);
      $admin->permission_config           = $admin->hasAccess(SM_ROOT . SM_CONFIG,                $admin->id);
      $admin->permission_chat             = $admin->hasAccess(SM_ROOT . SM_CHAT,                  $admin->id);
      $admin->permission_vote             = $admin->hasAccess(SM_ROOT . SM_VOTE,                  $admin->id);
      $admin->permission_password         = $admin->hasAccess(SM_ROOT . SM_PASSWORD,              $admin->id);
      $admin->permission_rcon             = $admin->hasAccess(SM_ROOT . SM_RCON,                  $admin->id);
      $admin->permission_cheats           = $admin->hasAccess(SM_ROOT . SM_CHEATS,                $admin->id);
      $admin->permission_custom1          = $admin->hasAccess(SM_ROOT . SM_CUSTOM1,               $admin->id);
      $admin->permission_custom2          = $admin->hasAccess(SM_ROOT . SM_CUSTOM2,               $admin->id);
      $admin->permission_custom3          = $admin->hasAccess(SM_ROOT . SM_CUSTOM3,               $admin->id);
      $admin->permission_custom4          = $admin->hasAccess(SM_ROOT . SM_CUSTOM4,               $admin->id);
      $admin->permission_custom5          = $admin->hasAccess(SM_ROOT . SM_CUSTOM5,               $admin->id);
      $admin->permission_custom6          = $admin->hasAccess(SM_ROOT . SM_CUSTOM6,               $admin->id);*/
      $admin->permission_add_admins       = $admin->hasAccess(array('OWNER', 'ADD_ADMINS'),       $admin->id);
      $admin->permission_delete_admins    = $admin->hasAccess(array('OWNER', 'DELETE_ADMINS'),    $admin->id);
      $admin->permission_edit_admins      = $admin->hasAccess(array('OWNER', 'EDIT_ADMINS'),      $admin->id);
      $admin->permission_import_admins    = $admin->hasAccess(array('OWNER', 'IMPORT_ADMINS'),    $admin->id);
      $admin->permission_list_admins      = $admin->hasAccess(array('OWNER', 'LIST_ADMINS'),      $admin->id);
      $admin->permission_add_groups       = $admin->hasAccess(array('OWNER', 'ADD_GROUPS'),       $admin->id);
      $admin->permission_delete_groups    = $admin->hasAccess(array('OWNER', 'DELETE_GROUPS'),    $admin->id);
      $admin->permission_edit_groups      = $admin->hasAccess(array('OWNER', 'EDIT_GROUPS'),      $admin->id);
      $admin->permission_import_groups    = $admin->hasAccess(array('OWNER', 'IMPORT_GROUPS'),    $admin->id);
      $admin->permission_list_groups      = $admin->hasAccess(array('OWNER', 'LIST_GROUPS'),      $admin->id);
      $admin->permission_add_games        = $admin->hasAccess(array('OWNER', 'ADD_GAMES'),         $admin->id);
      $admin->permission_delete_games     = $admin->hasAccess(array('OWNER', 'DELETE_GAMES'),      $admin->id);
      $admin->permission_edit_games       = $admin->hasAccess(array('OWNER', 'EDIT_GAMES'),        $admin->id);
      $admin->permission_list_games       = $admin->hasAccess(array('OWNER', 'LIST_GAMES'),        $admin->id);
      $admin->permission_add_servers      = $admin->hasAccess(array('OWNER', 'ADD_SERVERS'),      $admin->id);
      $admin->permission_delete_servers   = $admin->hasAccess(array('OWNER', 'DELETE_SERVERS'),   $admin->id);
      $admin->permission_edit_servers     = $admin->hasAccess(array('OWNER', 'EDIT_SERVERS'),     $admin->id);
      $admin->permission_list_servers     = $admin->hasAccess(array('OWNER', 'LIST_SERVERS'),     $admin->id);
      $admin->permission_add_bans         = $admin->hasAccess(array('OWNER', 'ADD_BANS'),         $admin->id);
      $admin->permission_add_group_bans   = $admin->hasAccess(array('OWNER', 'ADD_GROUP_BANS'),   $admin->id);
      $admin->permission_delete_bans      = $admin->hasAccess(array('OWNER', 'DELETE_BANS'),      $admin->id);
      $admin->permission_edit_all_bans    = $admin->hasAccess(array('OWNER', 'EDIT_ALL_BANS'),    $admin->id);
      $admin->permission_edit_own_bans    = $admin->hasAccess(array('OWNER', 'EDIT_OWN_BANS'),    $admin->id);
      $admin->permission_edit_group_bans  = $admin->hasAccess(array('OWNER', 'EDIT_GROUP_BANS'),  $admin->id);
      $admin->permission_import_bans      = $admin->hasAccess(array('OWNER', 'IMPORT_BANS'),      $admin->id);
      $admin->permission_unban_all_bans   = $admin->hasAccess(array('OWNER', 'UNBAN_ALL_BANS'),   $admin->id);
      $admin->permission_unban_group_bans = $admin->hasAccess(array('OWNER', 'UNBAN_GROUP_BANS'), $admin->id);
      $admin->permission_unban_own_bans   = $admin->hasAccess(array('OWNER', 'UNBAN_OWN_BANS'),   $admin->id);
      $admin->permission_ban_protests     = $admin->hasAccess(array('OWNER', 'BAN_PROTESTS'),     $admin->id);
      $admin->permission_ban_submissions  = $admin->hasAccess(array('OWNER', 'BAN_SUBMISSIONS'),  $admin->id);
      $admin->permission_notify_prot      = $admin->hasAccess(array('OWNER', 'NOTIFY_PROT'),      $admin->id);
      $admin->permission_notify_sub       = $admin->hasAccess(array('OWNER', 'NOTIFY_SUB'),       $admin->id);
      $admin->permission_settings         = $admin->hasAccess(array('OWNER', 'SETTINGS'),         $admin->id);
    }
    
    $user->permission_clear_actions  = $user->hasAccess(array('OWNER'));
    $user->permission_list_actions   = $user->hasAccess(array('OWNER', 'LIST_ACTIONS'));
    $user->permission_add_admins     = $user->hasAccess(array('OWNER', 'ADD_ADMINS'));
    $user->permission_delete_admins  = $user->hasAccess(array('OWNER', 'DELETE_ADMINS'));
    $user->permission_edit_admins    = $user->hasAccess(array('OWNER', 'EDIT_ADMINS'));
    $user->permission_import_admins  = $user->hasAccess(array('OWNER', 'IMPORT_ADMINS'));
    $user->permission_list_admins    = $user->hasAccess(array('OWNER', 'LIST_ADMINS'));
    $user->permission_list_overrides = $user->hasAccess(array('OWNER', 'LIST_OVERRIDES'));
    
    $template->action_title  = $language->admins;
    $template->actions       = $actions;
    $template->admins        = $admins;
    //$template->overrides     = $this->_registry->overrides;
    $template->servers       = $this->_registry->servers;
    $template->server_groups = $this->_registry->server_groups;
    $template->web_groups    = $this->_registry->web_groups;
    $template->end           = $admins_end;
    $template->order         = $order;
    $template->sort          = $sort;
    $template->start         = $admins_start;
    $template->total         = $count;
    $template->total_pages   = $pages;
    $template->display('admin_admins');
  }
  
  
  public function bans()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'ADD_BANS', 'EDIT_ALL_BANS', 'EDIT_GROUP_BANS', 'EDIT_OWN_BANS', 'BAN_PROTESTS', 'BAN_SUBMISSIONS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        switch($_POST['action'])
        {
          case 'add':
            if(!$user->hasAccess(array('OWNER', 'ADD_BANS')))
              throw new SBException($language->access_denied);
            
            $ban         = SB_API::createBan();
            $ban->ip     = $_POST['ip'];
            $ban->length = $_POST['length'];
            $ban->name   = $_POST['name'];
            $ban->reason = ($_POST['reason'] == 'other' ? $_POST['reason_other'] : $_POST['reason']);
            $ban->steam  = strtoupper($_POST['steam']);
            $ban->type   = $_POST['type'];
            $ban->save();
            
            // If one or more demos were uploaded, add them
            foreach($_FILES['demo'] as $file)
            {
              $demo           = SB_API::createDemo();
              $demo->ban_id   = $ban->id;
              $demo->name     = $file['name'];
              $demo->tmp_name = $file['tmp_name'];
              $demo->type     = $this->_registry->ban_type;
              $demo->save();
            }
            
            break;
          case 'import':
            if(!$user->hasAccess(array('OWNER', 'IMPORT_BANS')))
              throw new SBException($language->access_denied);
            
            SB_API::importBans($_FILES['file']['name'], $_FILES['file']['tmp_name']);
            break;
          default:
            throw new SBException($language->invalid_action);
        }
        
        exit(json_encode(array(
          'redirect' => $uri
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $protests             = $this->_registry->protests;
    $submissions          = $this->_registry->submissions;
    /*$archived_protests    = SB_API::getProtests(true);
    $archived_submissions = SB_API::getSubmissions(true);*/
    
    $user->permission_add_bans      = $user->hasAccess(array('OWNER', 'ADD_BANS'));
    $user->permission_edit_bans     = $user->hasAccess(array('OWNER', 'EDIT_ALL_BANS', 'EDIT_GROUP_BANS', 'EDIT_OWN_BANS'));
    $user->permission_import_bans   = $user->hasAccess(array('OWNER', 'IMPORT_BANS'));
    $user->permission_edit_comments = $user->hasAccess(array('OWNER'));
    $user->permission_list_comments = $user->is_admin();
    $user->permission_protests      = $user->hasAccess(array('OWNER', 'BAN_PROTESTS'));
    $user->permission_submissions   = $user->hasAccess(array('OWNER', 'BAN_SUBMISSIONS'));
    
    $template->action_title               = $language->bans;
    $template->protests                   = $protests;
    $template->submissions                = $submissions;
    /*$template->archived_protests          = $archived_protests['list'];
    $template->archived_submissions       = $archived_submissions['list'];*/
    $template->total_protests             = count($protests);
    $template->total_submissions          = count($submissions);
    /*$template->total_archived_protests    = $archived_protests['count'];
    $template->total_archived_submissions = $archived_submissions['count'];*/
    $template->display('admin_bans');
  }
  
  
  public function groups()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'ADD_GROUPS', 'DELETE_GROUPS', 'EDIT_GROUPS', 'LIST_GROUPS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        switch($_POST['action'])
        {
          case 'add':
            if(!$user->hasAccess(array('OWNER', 'ADD_GROUPS')))
              throw new SBException($language->access_denied);
            
            // Parse flags depending on group type
            switch($_POST['type'])
            {
              case SERVER_GROUPS:
                // If flag array contains root flag, only pass root flag, otherwise create flag string
                $flags = Util::in_array(SM_ROOT, $_POST['srv_flags']) ? SM_ROOT        : implode($_POST['srv_flags']);
                break;
              case WEB_GROUPS:
                // If flag array contains owner flag, only pass owner flag, otherwise pass entire flag array
                $flags = Util::in_array('OWNER', $_POST['web_flags']) ? array('OWNER') : $_POST['web_flags'];
                break;
              default:
                throw new SBException($language->invalid_type);
            }
            
            SB_API::addGroup($_POST['type'], $_POST['name'], $flags, isset($_POST['immunity']) && is_numeric($_POST['immunity']) ? $_POST['immunity'] : 0, $_POST['overrides']);
            break;
          case 'import':
            if(!$user->hasAccess(array('OWNER', 'IMPORT_GROUPS')))
              throw new SBException($language->access_denied);
            
            SB_API::importGroups($_FILES['file']['name'], $_FILES['file']['tmp_name']);
            break;
          default:
            throw new SBException($language->invalid_action);
        }
        
        exit(json_encode(array(
          'redirect' => $uri
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $server_groups = $this->_registry->server_groups;
    $web_groups    = $this->_registry->web_groups;
    
    /*foreach($server_groups as $group)
    {
      $permission_root                    = strpos($group->flags,                     SM_ROOT)        !== false;
      $group->permission_reservation      = $permission_root || strpos($group->flags, SM_RESERVATION) !== false;
      $group->permission_generic          = $permission_root || strpos($group->flags, SM_GENERIC)     !== false;
      $group->permission_kick             = $permission_root || strpos($group->flags, SM_KICK)        !== false;
      $group->permission_ban              = $permission_root || strpos($group->flags, SM_BAN)         !== false;
      $group->permission_unban            = $permission_root || strpos($group->flags, SM_UNBAN)       !== false;
      $group->permission_slay             = $permission_root || strpos($group->flags, SM_SLAY)        !== false;
      $group->permission_changemap        = $permission_root || strpos($group->flags, SM_CHANGEMAP)   !== false;
      $group->permission_cvar             = $permission_root || strpos($group->flags, SM_CVAR)        !== false;
      $group->permission_config           = $permission_root || strpos($group->flags, SM_CONFIG)      !== false;
      $group->permission_chat             = $permission_root || strpos($group->flags, SM_CHAT)        !== false;
      $group->permission_vote             = $permission_root || strpos($group->flags, SM_VOTE)        !== false;
      $group->permission_password         = $permission_root || strpos($group->flags, SM_PASSWORD)    !== false;
      $group->permission_rcon             = $permission_root || strpos($group->flags, SM_RCON)        !== false;
      $group->permission_cheats           = $permission_root || strpos($group->flags, SM_CHEATS)      !== false;
      $group->permission_custom1          = $permission_root || strpos($group->flags, SM_CUSTOM1)     !== false;
      $group->permission_custom2          = $permission_root || strpos($group->flags, SM_CUSTOM2)     !== false;
      $group->permission_custom3          = $permission_root || strpos($group->flags, SM_CUSTOM3)     !== false;
      $group->permission_custom4          = $permission_root || strpos($group->flags, SM_CUSTOM4)     !== false;
      $group->permission_custom5          = $permission_root || strpos($group->flags, SM_CUSTOM5)     !== false;
      $group->permission_custom6          = $permission_root || strpos($group->flags, SM_CUSTOM6)     !== false;
      $group->permission_root             = $permission_root;
    }*/
    
    foreach($web_groups as $group)
    {
      $permission_owner                   = Util::in_array('OWNER',                                 $group->flags);
      $group->permission_add_admins       = $permission_owner || Util::in_array('ADD_ADMINS',       $group->flags);
      $group->permission_delete_admins    = $permission_owner || Util::in_array('DELETE_ADMINS',    $group->flags);
      $group->permission_edit_admins      = $permission_owner || Util::in_array('EDIT_ADMINS',      $group->flags);
      $group->permission_import_admins    = $permission_owner || Util::in_array('IMPORT_ADMINS',    $group->flags);
      $group->permission_list_admins      = $permission_owner || Util::in_array('LIST_ADMINS',      $group->flags);
      $group->permission_add_groups       = $permission_owner || Util::in_array('ADD_GROUPS',       $group->flags);
      $group->permission_delete_groups    = $permission_owner || Util::in_array('DELETE_GROUPS',    $group->flags);
      $group->permission_edit_groups      = $permission_owner || Util::in_array('EDIT_GROUPS',      $group->flags);
      $group->permission_import_groups    = $permission_owner || Util::in_array('IMPORT_GROUPS',    $group->flags);
      $group->permission_list_groups      = $permission_owner || Util::in_array('LIST_GROUPS',      $group->flags);
      $group->permission_add_games        = $permission_owner || Util::in_array('ADD_GAMES',         $group->flags);
      $group->permission_delete_games     = $permission_owner || Util::in_array('DELETE_GAMES',      $group->flags);
      $group->permission_edit_games       = $permission_owner || Util::in_array('EDIT_GAMES',        $group->flags);
      $group->permission_list_games       = $permission_owner || Util::in_array('LIST_GAMES',        $group->flags);
      $group->permission_add_servers      = $permission_owner || Util::in_array('ADD_SERVERS',      $group->flags);
      $group->permission_delete_servers   = $permission_owner || Util::in_array('DELETE_SERVERS',   $group->flags);
      $group->permission_edit_servers     = $permission_owner || Util::in_array('EDIT_SERVERS',     $group->flags);
      $group->permission_list_servers     = $permission_owner || Util::in_array('LIST_SERVERS',     $group->flags);
      $group->permission_import_servers   = $permission_owner || Util::in_array('IMPORT_SERVERS',   $group->flags);
      $group->permission_add_bans         = $permission_owner || Util::in_array('ADD_BANS',         $group->flags);
      $group->permission_delete_bans      = $permission_owner || Util::in_array('DELETE_BANS',      $group->flags);
      $group->permission_edit_all_bans    = $permission_owner || Util::in_array('EDIT_ALL_BANS',    $group->flags);
      $group->permission_edit_group_bans  = $permission_owner || Util::in_array('EDIT_GROUP_BANS',  $group->flags);
      $group->permission_edit_own_bans    = $permission_owner || Util::in_array('EDIT_OWN_BANS',    $group->flags);
      $group->permission_import_bans      = $permission_owner || Util::in_array('IMPORT_BANS',      $group->flags);
      $group->permission_unban_all_bans   = $permission_owner || Util::in_array('UNBAN_ALL_BANS',   $group->flags);
      $group->permission_unban_group_bans = $permission_owner || Util::in_array('UNBAN_GROUP_BANS', $group->flags);
      $group->permission_unban_own_bans   = $permission_owner || Util::in_array('UNBAN_OWN_BANS',   $group->flags);
      $group->permission_ban_protests     = $permission_owner || Util::in_array('BAN_PROTESTS',     $group->flags);
      $group->permission_ban_submissions  = $permission_owner || Util::in_array('BAN_SUBMISSIONS',  $group->flags);
      $group->permission_notify_prot      = $permission_owner || Util::in_array('NOTIFY_PROT',      $group->flags);
      $group->permission_notify_sub       = $permission_owner || Util::in_array('NOTIFY_SUB',       $group->flags);
      $group->permission_settings         = $permission_owner || Util::in_array('SETTINGS',         $group->flags);
      $group->permission_owner            = $permission_owner;
    }
    
    $user->permission_add_groups    = $user->hasAccess(array('OWNER', 'ADD_GROUPS'));
    $user->permission_delete_groups = $user->hasAccess(array('OWNER', 'DELETE_GROUPS'));
    $user->permission_edit_groups   = $user->hasAccess(array('OWNER', 'EDIT_GROUPS'));
    $user->permission_import_groups = $user->hasAccess(array('OWNER', 'IMPORT_GROUPS'));
    $user->permission_list_groups   = $user->hasAccess(array('OWNER', 'LIST_GROUPS'));
    
    $template->action_title  = $language->groups;
    $template->server_groups = $server_groups;
    $template->web_groups    = $web_groups;
    $template->display('admin_groups');
  }
  
  
  public function games()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'ADD_GAMES', 'DELETE_GAMES', 'EDIT_GAMES', 'LIST_GAMES')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        switch($_POST['action'])
        {
          case 'add':
            if(!$user->hasAccess(array('OWNER', 'ADD_GAMES')))
              throw new SBException($language->access_denied);
            
            $game         = SB_API::createGame();
            $game->name   = $_POST['name'];
            $game->folder = $_POST['folder'];
            $game->icon   = $_POST['icon'];
            $game->save();
            
            break;
          default:
            throw new SBException($language->invalid_action);
        }
        
        exit(json_encode(array(
          'redirect' => $uri
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $user->permission_add_games    = $user->hasAccess(array('OWNER', 'ADD_GAMES'));
    $user->permission_delete_games = $user->hasAccess(array('OWNER', 'DELETE_GAMES'));
    $user->permission_edit_games   = $user->hasAccess(array('OWNER', 'EDIT_GAMES'));
    $user->permission_list_games   = $user->hasAccess(array('OWNER', 'LIST_GAMES'));
    
    $games = $this->_registry->games;
    //Util::object_qsort($games, 'name');
    
    $template->action_title = $language->games;
    $template->games        = $games;
    $template->display('admin_games');
  }
  
  
  public function servers()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'ADD_SERVERS', 'DELETE_SERVERS', 'EDIT_SERVERS', 'LIST_SERVERS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        switch($_POST['action'])
        {
          case 'add':
            if(!$user->hasAccess(array('OWNER', 'ADD_SERVERS')))
              throw new SBException($language->access_denied);
            if($_POST['rcon'] != $_POST['rcon_confirm'])
                throw new SBException($language->passwords_do_not_match);
            
            SB_API::addServer($_POST['ip'], $_POST['port'], $_POST['rcon'], $_POST['game'], isset($_POST['enabled']), $_POST['groups']);
            break;
          case 'import':
            if(!$user->hasAccess(array('OWNER', 'IMPORT_SERVERS')))
              throw new SBException($language->access_denied);
            
            SB_API::importServers($_FILES['file']['name'], $_FILES['file']['tmp_name']);
            break;
          default:
            throw new SBException($language->invalid_action);
        }
        
        exit(json_encode(array(
          'redirect' => $uri,
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage(),
        )));
      }
    }
    
    $user->permission_config         = $user->hasAccess(array('OWNER'));
    //$user->permission_rcon           = $user->hasAccess(SM_RCON . SM_ROOT);
    $user->permission_add_servers    = $user->hasAccess(array('OWNER', 'ADD_SERVERS'));
    $user->permission_delete_servers = $user->hasAccess(array('OWNER', 'DELETE_SERVERS'));
    $user->permission_edit_servers   = $user->hasAccess(array('OWNER', 'EDIT_SERVERS'));
    $user->permission_import_servers = $user->hasAccess(array('OWNER', 'IMPORT_SERVERS'));
    $user->permission_list_servers   = $user->hasAccess(array('OWNER', 'LIST_SERVERS'));
    
    $template->action_title  = $language->servers;
    $template->server_groups = $this->_registry->server_groups;
    $template->games         = $this->_registry->games;
    $template->servers       = $this->_registry->servers;
    $template->display('admin_servers');
  }
  
  
  public function settings()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'SETTINGS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        switch($_POST['action'])
        {
          case 'settings':
            $settings->enable_debug       = isset($_POST['enable_debug']);
            $settings->enable_protest     = isset($_POST['enable_protest']);
            $settings->enable_smtp        = isset($_POST['enable_smtp']);
            $settings->enable_submit      = isset($_POST['enable_submit']);
            $settings->bans_hide_admin    = isset($_POST['bans_hide_admin']);
            $settings->bans_hide_ip       = isset($_POST['bans_hide_ip']);
            $settings->bans_public_export = isset($_POST['bans_public_export']);
            $settings->disable_log_popup  = isset($_POST['disable_log_popup']);
            $settings->summer_time        = isset($_POST['summer_time']);
            
            if(isset($_POST['dashboard_text'])      && !empty($_POST['dashboard_text'])      && is_string($_POST['dashboard_text']))
              $settings->dashboard_text      = $_POST['dashboard_text'];
            if(isset($_POST['dashboard_title'])     && !empty($_POST['dashboard_title'])     && is_string($_POST['dashboard_title']))
              $settings->dashboard_title     = $_POST['dashboard_title'];
            if(isset($_POST['date_format'])         && !empty($_POST['date_format'])         && is_string($_POST['date_format']))
              $settings->date_format         = $_POST['date_format'];
            if(isset($_POST['default_page'])        && !empty($_POST['default_page'])        && is_numeric($_POST['default_page']))
              $settings->default_page        = $_POST['default_page'];
            if(isset($_POST['items_per_page'])      && !empty($_POST['items_per_page'])      && is_numeric($_POST['items_per_page']))
              $settings->items_per_page      = $_POST['items_per_page'];
            if(isset($_POST['password_min_length']) && !empty($_POST['password_min_length']) && is_numeric($_POST['password_min_length']))
              $settings->password_min_length = $_POST['password_min_length'];
            if(isset($_POST['smtp_host'])           && !empty($_POST['smtp_host'])           && is_string($_POST['smtp_host']))
              $settings->smtp_host           = $_POST['smtp_host'];
            if(isset($_POST['smtp_password'])       && !empty($_POST['smtp_password'])       && is_string($_POST['smtp_password']))
              $settings->smtp_password       = $_POST['smtp_password'];
            if(isset($_POST['smtp_port'])           && !empty($_POST['smtp_port'])           && is_string($_POST['smtp_port']))
              $settings->smtp_port           = $_POST['smtp_port'];
            if(isset($_POST['smtp_secure'])         && is_string($_POST['smtp_secure']))
              $settings->smtp_secure         = $_POST['smtp_secure'];
            if(isset($_POST['smtp_username'])       && !empty($_POST['smtp_username'])      && is_string($_POST['smtp_username']))
              $settings->smtp_username       = $_POST['smtp_username'];
            if(isset($_POST['timezone'])            && !empty($_POST['timezone'])           && is_numeric($_POST['timezone']))
              $settings->timezone            = $_POST['timezone'];
            
            $settings->save();
            break;
          case 'plugins':
            foreach($this->_registry->plugins as $name => $plugin)
            {
              if(!isset($_POST['plugins'][$name]))
                continue;
              
              $plugin->enabled = $_POST['plugins'][$name];
            }
            
            break;
          default:
            throw new SBException($language->invalid_action);
        }
        
        exit(json_encode(array(
          'redirect' => $uri
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $languages = array();
    $themes    = array();
    
    // Parse languages
    foreach($this->_registry->languages as $_language)
    {
      $languages[$_language] = new SBLanguage($_language);
    }
    
    // Parse themes
    foreach($this->_registry->themes as $_theme)
    {
      $themes[$_theme] = new SBTheme($_theme);
    }
    
    // Sort languages and themes by name
    Util::object_qsort($languages, 'getInfo("name")');
    Util::object_qsort($themes,    'name');
    
    $template->action_title          = $language->settings;
    $template->permission_clear_logs = $user->hasAccess(array('OWNER'));
    $template->admins                = $this->_registry->admins;
    $template->languages             = $languages;
    //$template->logs                  = $this->_registry->logs;
    $template->plugins               = $this->_registry->plugins;
    $template->themes                = $themes;
    //$template->total_pages           = $pages;
    
    $template->display('admin_settings');
  }
}