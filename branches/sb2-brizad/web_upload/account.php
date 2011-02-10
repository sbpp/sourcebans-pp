<?php
require_once 'api.php';

$config   = Env::get('config');
$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['your_account']), !isset($_GET['nofullpage']));

try
{
  if(!$userbank->is_logged_in())
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      switch($_POST['action'])
      {
        case 'email':
          SB_API::editAdmin($userbank->GetID(), null, null, null, $_POST['email']);
          break;
        case 'password':
          SB_API::editAdmin($userbank->GetID(), null, null, null, null, $_POST['password']);
          break;
        case 'settings':
          SB_API::editAdmin($userbank->GetID(), null, null, null, null, null, null, null, null, $_POST['theme'], $_POST['language']);
          break;
        case 'srvpassword':
          SB_API::editAdmin($userbank->GetID(), null, null, null, null, null, $_POST['srvpassword']);
          break;
        default:
          throw new Exception($phrases['invalid_action']);
      }
      
      exit(json_encode(array(
        'redirect' => Util::buildQuery()
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
  foreach(glob(LANGUAGES_DIR . '*.lang') as $language)
  {
    $code         = pathinfo(LANGUAGES_DIR . $language, PATHINFO_FILENAME);
    $translations = SB_API::getTranslations($code);
    
    $languages[]  = array('code' => $code,
                          'name' => $translations['info']['name']);
  }
  // Parse themes
  foreach(scandir(THEMES_DIR) as $theme)
  {
    $file = THEMES_DIR . $theme . '/theme.info';
    if(!file_exists($file))
      continue;
    
    $info     = parse_ini_file($file);
    $themes[] = array('dir'  => $theme,
                      'name' => $info['name']);
  }
  // Sort languages and themes by name
  Util::array_qsort($languages, 'name');
  Util::array_qsort($themes,    'name');
  
  $page->assign('languages',                   $languages);
  $page->assign('themes',                      $themes);
  $page->assign('min_pass_len',                $config['config.password.minlength']);
  $page->assign('password_set',                $userbank->GetProperty('srv_password') != '');
  $page->assign('user_email',                  $userbank->GetProperty('email'));
  $page->assign('user_language',               $userbank->GetProperty('language'));
  $page->assign('user_srv_flags',              $userbank->GetProperty('srv_flags'));
  $page->assign('user_theme',                  $userbank->GetProperty('theme'));
  $page->assign('user_web_flags',              $userbank->is_admin());
  $page->assign('permission_reservation',      $userbank->HasAccess(SM_ROOT . SM_RESERVATION));
  $page->assign('permission_generic',          $userbank->HasAccess(SM_ROOT . SM_GENERIC));
  $page->assign('permission_kick',             $userbank->HasAccess(SM_ROOT . SM_KICK));
  $page->assign('permission_ban',              $userbank->HasAccess(SM_ROOT . SM_BAN));
  $page->assign('permission_unban',            $userbank->HasAccess(SM_ROOT . SM_UNBAN));
  $page->assign('permission_slay',             $userbank->HasAccess(SM_ROOT . SM_SLAY));
  $page->assign('permission_changemap',        $userbank->HasAccess(SM_ROOT . SM_CHANGEMAP));
  $page->assign('permission_cvar',             $userbank->HasAccess(SM_ROOT . SM_CVAR));
  $page->assign('permission_config',           $userbank->HasAccess(SM_ROOT . SM_CONFIG));
  $page->assign('permission_chat',             $userbank->HasAccess(SM_ROOT . SM_CHAT));
  $page->assign('permission_vote',             $userbank->HasAccess(SM_ROOT . SM_VOTE));
  $page->assign('permission_password',         $userbank->HasAccess(SM_ROOT . SM_PASSWORD));
  $page->assign('permission_rcon',             $userbank->HasAccess(SM_ROOT . SM_RCON));
  $page->assign('permission_cheats',           $userbank->HasAccess(SM_ROOT . SM_CHEATS));
  $page->assign('permission_custom1',          $userbank->HasAccess(SM_ROOT . SM_CUSTOM1));
  $page->assign('permission_custom2',          $userbank->HasAccess(SM_ROOT . SM_CUSTOM2));
  $page->assign('permission_custom3',          $userbank->HasAccess(SM_ROOT . SM_CUSTOM3));
  $page->assign('permission_custom4',          $userbank->HasAccess(SM_ROOT . SM_CUSTOM4));
  $page->assign('permission_custom5',          $userbank->HasAccess(SM_ROOT . SM_CUSTOM5));
  $page->assign('permission_custom6',          $userbank->HasAccess(SM_ROOT . SM_CUSTOM6));
  $page->assign('permission_add_admins',       $userbank->HasAccess(array('OWNER', 'ADD_ADMINS')));
  $page->assign('permission_delete_admins',    $userbank->HasAccess(array('OWNER', 'DELETE_ADMINS')));
  $page->assign('permission_edit_admins',      $userbank->HasAccess(array('OWNER', 'EDIT_ADMINS')));
  $page->assign('permission_import_admins',    $userbank->HasAccess(array('OWNER', 'IMPORT_ADMINS')));
  $page->assign('permission_list_admins',      $userbank->HasAccess(array('OWNER', 'LIST_ADMINS')));
  $page->assign('permission_add_groups',       $userbank->HasAccess(array('OWNER', 'ADD_GROUPS')));
  $page->assign('permission_delete_groups',    $userbank->HasAccess(array('OWNER', 'DELETE_GROUPS')));
  $page->assign('permission_edit_groups',      $userbank->HasAccess(array('OWNER', 'EDIT_GROUPS')));
  $page->assign('permission_import_groups',    $userbank->HasAccess(array('OWNER', 'IMPORT_GROUPS')));
  $page->assign('permission_list_groups',      $userbank->HasAccess(array('OWNER', 'LIST_GROUPS')));
  $page->assign('permission_add_mods',         $userbank->HasAccess(array('OWNER', 'ADD_MODS')));
  $page->assign('permission_delete_mods',      $userbank->HasAccess(array('OWNER', 'DELETE_MODS')));
  $page->assign('permission_edit_mods',        $userbank->HasAccess(array('OWNER', 'EDIT_MODS')));
  $page->assign('permission_list_mods',        $userbank->HasAccess(array('OWNER', 'LIST_MODS')));
  $page->assign('permission_add_servers',      $userbank->HasAccess(array('OWNER', 'ADD_SERVERS')));
  $page->assign('permission_delete_servers',   $userbank->HasAccess(array('OWNER', 'DELETE_SERVERS')));
  $page->assign('permission_edit_servers',     $userbank->HasAccess(array('OWNER', 'EDIT_SERVERS')));
  $page->assign('permission_list_servers',     $userbank->HasAccess(array('OWNER', 'LIST_SERVERS')));
  $page->assign('permission_add_bans',         $userbank->HasAccess(array('OWNER', 'ADD_BANS')));
  $page->assign('permission_delete_bans',      $userbank->HasAccess(array('OWNER', 'DELETE_BANS')));
  $page->assign('permission_edit_all_bans',    $userbank->HasAccess(array('OWNER', 'EDIT_ALL_BANS')));
  $page->assign('permission_edit_group_bans',  $userbank->HasAccess(array('OWNER', 'EDIT_GROUP_BANS')));
  $page->assign('permission_edit_own_bans',    $userbank->HasAccess(array('OWNER', 'EDIT_OWN_BANS')));
  $page->assign('permission_import_bans',      $userbank->HasAccess(array('OWNER', 'IMPORT_BANS')));
  $page->assign('permission_unban_all_bans',   $userbank->HasAccess(array('OWNER', 'UNBAN_ALL_BANS')));
  $page->assign('permission_unban_group_bans', $userbank->HasAccess(array('OWNER', 'UNBAN_GROUP_BANS')));
  $page->assign('permission_unban_own_bans',   $userbank->HasAccess(array('OWNER', 'UNBAN_OWN_BANS')));
  $page->assign('permission_ban_protests',     $userbank->HasAccess(array('OWNER', 'BAN_PROTESTS')));
  $page->assign('permission_ban_submissions',  $userbank->HasAccess(array('OWNER', 'BAN_SUBMISSIONS')));
  $page->assign('permission_notify_prot',      $userbank->HasAccess(array('OWNER', 'NOTIFY_PROT')));
  $page->assign('permission_notify_sub',       $userbank->HasAccess(array('OWNER', 'NOTIFY_SUB')));
  $page->assign('permission_settings',         $userbank->HasAccess(array('OWNER', 'SETTINGS')));
  $page->display('page_account');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>