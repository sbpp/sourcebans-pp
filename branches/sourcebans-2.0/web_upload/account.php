<?php
require_once 'init.php';

$config   = Env::get('config');
$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['your_account']));

try
{
  if(!$userbank->is_logged_in())
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    switch($_POST['action'])
    {
      case 'email':
        AdminsWriter::edit($userbank->GetID(), null, null, null, $_POST['email'], null, null, null, null, null, null);
        break;
      case 'password':
        AdminsWriter::edit($userbank->GetID(), null, null, null, null, $userbank->encrypt_password($_POST['password']), null, null, null, null, null);
        break;
      case 'settings':
        AdminsWriter::edit($userbank->GetID(), null, null, null, null, null, null, null, null, $_POST['theme'], $_POST['language']);
        break;
      case 'srvpassword':
        AdminsWriter::edit($userbank->GetID(), null, null, null, null, null, $_POST['srvpassword'], null, null, null, null);
    }
    
    Util::redirect();
  }
  
  $languages = array();
  $themes    = array();
  
  // Parse languages
  foreach(glob(LANGUAGES_DIR . '*.lang') as $language)
  {
    $code                          = pathinfo(LANGUAGES_DIR . $language, PATHINFO_FILENAME);
    $translations_reader           = new TranslationsReader();
    $translations_reader->language = $code;
    $translations                  = $translations_reader->executeCached(ONE_DAY);
    
    $languages[]                   = array('code' => $code,
                                           'name' => $translations['info']['name']);
  }
  // Parse themes
  foreach(scandir(THEMES_DIR) as $theme)
  {
    $file = THEMES_DIR . $theme . '/theme.info';
    if(!file_exists($file))
      continue;
    
    $info     = Util::parse_ini_file($file);
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
  $page->assign('permission_add_admins',       $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_ADMINS')));
  $page->assign('permission_delete_admins',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_ADMINS')));
  $page->assign('permission_edit_admins',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ADMINS')));
  $page->assign('permission_import_admins',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_ADMINS')));
  $page->assign('permission_list_admins',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_ADMINS')));
  $page->assign('permission_add_groups',       $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_GROUPS')));
  $page->assign('permission_delete_groups',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_GROUPS')));
  $page->assign('permission_edit_groups',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_GROUPS')));
  $page->assign('permission_import_groups',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_GROUPS')));
  $page->assign('permission_list_groups',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_GROUPS')));
  $page->assign('permission_add_mods',         $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_MODS')));
  $page->assign('permission_delete_mods',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_MODS')));
  $page->assign('permission_edit_mods',        $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_MODS')));
  $page->assign('permission_list_mods',        $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_MODS')));
  $page->assign('permission_add_servers',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_SERVERS')));
  $page->assign('permission_delete_servers',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_SERVERS')));
  $page->assign('permission_edit_servers',     $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_SERVERS')));
  $page->assign('permission_list_servers',     $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_SERVERS')));
  $page->assign('permission_add_bans',         $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_BANS')));
  $page->assign('permission_delete_bans',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_BANS')));
  $page->assign('permission_edit_all_bans',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ALL_BANS')));
  $page->assign('permission_edit_group_bans',  $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_GROUP_BANS')));
  $page->assign('permission_edit_own_bans',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_OWN_BANS')));
  $page->assign('permission_import_bans',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_BANS')));
  $page->assign('permission_unban_all_bans',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_UNBAN_ALL_BANS')));
  $page->assign('permission_unban_group_bans', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_UNBAN_GROUP_BANS')));
  $page->assign('permission_unban_own_bans',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_UNBAN_OWN_BANS')));
  $page->assign('permission_ban_protests',     $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS')));
  $page->assign('permission_ban_submissions',  $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')));
  $page->assign('permission_notify_prot',      $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_NOTIFY_PROT')));
  $page->assign('permission_notify_sub',       $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_NOTIFY_SUB')));
  $page->assign('permission_settings',         $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_SETTINGS')));
  $page->display('page_account');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>