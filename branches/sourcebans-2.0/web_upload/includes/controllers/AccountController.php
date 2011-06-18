<?php
class AccountController extends BaseController
{
  protected function _title()
  {
    return ucwords($this->_registry->user->language->your_account);
  }
  
  
  public function index()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->is_logged_in())
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        switch($_POST['action'])
        {
          case 'email':
            if($_POST['emsil'] != $_POST['email_confirm'])
              throw new SBException($language->fields_do_not_match);
            
            $user->email = $_POST['email'];
            break;
          case 'password':
            if($_POST['password'] != $_POST['password_confirm'])
              throw new SBException($language->passwords_do_not_match);
            
            $user->password = $_POST['password'];
            break;
          case 'settings':
            $user->language = $_POST['language'];
            $user->theme    = $_POST['theme'];
            break;
          case 'srv_password':
            if($_POST['srv_password'] != $_POST['srv_password_confirm'])
              throw new SBException($language->passwords_do_not_match);
            
            $user->srv_password = $_POST['srv_password'];
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
    foreach($this->_registry->languages as $language)
    {
      $languages[$language] = new SBLanguage($language);
    }
    
    // Parse themes
    foreach($this->_registry->themes as $theme)
    {
      $themes[$theme] = new SBTheme($theme);
    }
    
    // Sort languages and themes by name
    Util::object_qsort($languages, 'getInfo("name")');
    Util::object_qsort($themes,    'name');
    
    /*$user->permission_reservation      = $user->hasAccess(SM_ROOT . SM_RESERVATION);
    $user->permission_generic          = $user->hasAccess(SM_ROOT . SM_GENERIC);
    $user->permission_kick             = $user->hasAccess(SM_ROOT . SM_KICK);
    $user->permission_ban              = $user->hasAccess(SM_ROOT . SM_BAN);
    $user->permission_unban            = $user->hasAccess(SM_ROOT . SM_UNBAN);
    $user->permission_slay             = $user->hasAccess(SM_ROOT . SM_SLAY);
    $user->permission_changemap        = $user->hasAccess(SM_ROOT . SM_CHANGEMAP);
    $user->permission_cvar             = $user->hasAccess(SM_ROOT . SM_CVAR);
    $user->permission_config           = $user->hasAccess(SM_ROOT . SM_CONFIG);
    $user->permission_chat             = $user->hasAccess(SM_ROOT . SM_CHAT);
    $user->permission_vote             = $user->hasAccess(SM_ROOT . SM_VOTE);
    $user->permission_password         = $user->hasAccess(SM_ROOT . SM_PASSWORD);
    $user->permission_rcon             = $user->hasAccess(SM_ROOT . SM_RCON);
    $user->permission_cheats           = $user->hasAccess(SM_ROOT . SM_CHEATS);
    $user->permission_custom1          = $user->hasAccess(SM_ROOT . SM_CUSTOM1);
    $user->permission_custom2          = $user->hasAccess(SM_ROOT . SM_CUSTOM2);
    $user->permission_custom3          = $user->hasAccess(SM_ROOT . SM_CUSTOM3);
    $user->permission_custom4          = $user->hasAccess(SM_ROOT . SM_CUSTOM4);
    $user->permission_custom5          = $user->hasAccess(SM_ROOT . SM_CUSTOM5);
    $user->permission_custom6          = $user->hasAccess(SM_ROOT . SM_CUSTOM6);*/
    $user->permission_add_admins       = $user->hasAccess(array('OWNER', 'ADD_ADMINS'));
    $user->permission_delete_admins    = $user->hasAccess(array('OWNER', 'DELETE_ADMINS'));
    $user->permission_edit_admins      = $user->hasAccess(array('OWNER', 'EDIT_ADMINS'));
    $user->permission_import_admins    = $user->hasAccess(array('OWNER', 'IMPORT_ADMINS'));
    $user->permission_list_admins      = $user->hasAccess(array('OWNER', 'LIST_ADMINS'));
    $user->permission_add_groups       = $user->hasAccess(array('OWNER', 'ADD_GROUPS'));
    $user->permission_delete_groups    = $user->hasAccess(array('OWNER', 'DELETE_GROUPS'));
    $user->permission_edit_groups      = $user->hasAccess(array('OWNER', 'EDIT_GROUPS'));
    $user->permission_import_groups    = $user->hasAccess(array('OWNER', 'IMPORT_GROUPS'));
    $user->permission_list_groups      = $user->hasAccess(array('OWNER', 'LIST_GROUPS'));
    $user->permission_add_games        = $user->hasAccess(array('OWNER', 'ADD_GAMES'));
    $user->permission_delete_games     = $user->hasAccess(array('OWNER', 'DELETE_GAMES'));
    $user->permission_edit_games       = $user->hasAccess(array('OWNER', 'EDIT_GAMES'));
    $user->permission_list_games       = $user->hasAccess(array('OWNER', 'LIST_GAMES'));
    $user->permission_add_servers      = $user->hasAccess(array('OWNER', 'ADD_SERVERS'));
    $user->permission_delete_servers   = $user->hasAccess(array('OWNER', 'DELETE_SERVERS'));
    $user->permission_edit_servers     = $user->hasAccess(array('OWNER', 'EDIT_SERVERS'));
    $user->permission_list_servers     = $user->hasAccess(array('OWNER', 'LIST_SERVERS'));
    $user->permission_add_bans         = $user->hasAccess(array('OWNER', 'ADD_BANS'));
    $user->permission_delete_bans      = $user->hasAccess(array('OWNER', 'DELETE_BANS'));
    $user->permission_edit_all_bans    = $user->hasAccess(array('OWNER', 'EDIT_ALL_BANS'));
    $user->permission_edit_group_bans  = $user->hasAccess(array('OWNER', 'EDIT_GROUP_BANS'));
    $user->permission_edit_own_bans    = $user->hasAccess(array('OWNER', 'EDIT_OWN_BANS'));
    $user->permission_import_bans      = $user->hasAccess(array('OWNER', 'IMPORT_BANS'));
    $user->permission_unban_all_bans   = $user->hasAccess(array('OWNER', 'UNBAN_ALL_BANS'));
    $user->permission_unban_group_bans = $user->hasAccess(array('OWNER', 'UNBAN_GROUP_BANS'));
    $user->permission_unban_own_bans   = $user->hasAccess(array('OWNER', 'UNBAN_OWN_BANS'));
    $user->permission_ban_protests     = $user->hasAccess(array('OWNER', 'BAN_PROTESTS'));
    $user->permission_ban_submissions  = $user->hasAccess(array('OWNER', 'BAN_SUBMISSIONS'));
    $user->permission_notify_prot      = $user->hasAccess(array('OWNER', 'NOTIFY_PROT'));
    $user->permission_notify_sub       = $user->hasAccess(array('OWNER', 'NOTIFY_SUB'));
    $user->permission_settings         = $user->hasAccess(array('OWNER', 'SETTINGS'));
    
    $template->languages = $languages;
    $template->themes    = $themes;
    $template->display('account');
  }
}