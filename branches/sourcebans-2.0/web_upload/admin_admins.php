<?php
require_once 'init.php';
require_once READERS_DIR . 'actions.php';
require_once READERS_DIR . 'admins.php';
require_once READERS_DIR . 'counts.php';
require_once READERS_DIR . 'groups.php';
require_once READERS_DIR . 'servers.php';
require_once WRITERS_DIR . 'admins.php';

$config   = Env::get('config');
$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['admins']);

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_ADMINS', 'ADMIN_DELETE_ADMINS', 'ADMIN_EDIT_ADMINS', 'ADMIN_LIST_ADMINS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    switch($_POST['action'])
    {
      case 'add':
        if($_POST['password'] != $_POST['password_confirm'])
          throw new Exception('Passwords don\'t match');
        
        AdminsWriter::add($_POST['name'], $_POST['auth'], $_POST['identity'], $_POST['email'], $_POST['password'], isset($_POST['srv_password']), $_POST['srv_groups'], $_POST['web_group']);
        break;
      case 'import':
        AdminsWriter::import($_FILES['file']['name'], $_FILES['file']['tmp_name']);
    }
    
    Util::redirect();
  }
  
  $actions_reader       = new ActionsReader();
  $admins_reader        = new AdminsReader();
  $counts_reader        = new CountsReader();
  $groups_reader        = new GroupsReader();
  $servers_reader       = new ServersReader();
  
  $limit                = 25;
  $admins_reader->limit = $limit;
  
  if(isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 1)
    $admins_reader->page   = $_GET['page'];
  if(isset($_GET['search']))
    $admins_reader->search = $_GET['search'];
  if(isset($_GET['sort']) && is_string($_GET['sort']))
    $admins_reader->sort   = $_GET['sort'];
  if(isset($_GET['type']))
    $admins_reader->type   = $_GET['type'];
  
  $actions              = $actions_reader->executeCached(ONE_MINUTE * 5);
  $admins               = $admins_reader->executeCached(ONE_MINUTE  * 5);
  $counts               = $counts_reader->executeCached(ONE_MINUTE  * 5);
  $servers              = $servers_reader->executeCached(ONE_MINUTE * 5);
  
  $groups_reader->type  = WEB_ADMIN_GROUPS;
  $web_admin_groups     = $groups_reader->executeCached(ONE_MINUTE  * 5);
  
  $groups_reader->type  = SERVER_ADMIN_GROUPS;
  $server_admin_groups  = $groups_reader->executeCached(ONE_MINUTE  * 5);
  
  $admins_start         = ($admins_reader->page - 1) * $limit;
  $admins_end           = $admins_start              + $limit;
  $pages                = ceil($counts['admins']     / $limit);
  if($admins_end > $counts['admins'])
    $admins_end = $counts['admins'];
  
  foreach($admins as $id => &$admin)
  {
    $admin['permission_reservation']      = $userbank->HasAccess(SM_ROOT . SM_RESERVATION,                       $id);
    $admin['permission_generic']          = $userbank->HasAccess(SM_ROOT . SM_GENERIC,                           $id);
    $admin['permission_kick']             = $userbank->HasAccess(SM_ROOT . SM_KICK,                              $id);
    $admin['permission_ban']              = $userbank->HasAccess(SM_ROOT . SM_BAN,                               $id);
    $admin['permission_unban']            = $userbank->HasAccess(SM_ROOT . SM_UNBAN,                             $id);
    $admin['permission_slay']             = $userbank->HasAccess(SM_ROOT . SM_SLAY,                              $id);
    $admin['permission_changemap']        = $userbank->HasAccess(SM_ROOT . SM_CHANGEMAP,                         $id);
    $admin['permission_cvar']             = $userbank->HasAccess(SM_ROOT . SM_CVAR,                              $id);
    $admin['permission_config']           = $userbank->HasAccess(SM_ROOT . SM_CONFIG,                            $id);
    $admin['permission_chat']             = $userbank->HasAccess(SM_ROOT . SM_CHAT,                              $id);
    $admin['permission_vote']             = $userbank->HasAccess(SM_ROOT . SM_VOTE,                              $id);
    $admin['permission_password']         = $userbank->HasAccess(SM_ROOT . SM_PASSWORD,                          $id);
    $admin['permission_rcon']             = $userbank->HasAccess(SM_ROOT . SM_RCON,                              $id);
    $admin['permission_cheats']           = $userbank->HasAccess(SM_ROOT . SM_CHEATS,                            $id);
    $admin['permission_custom1']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM1,                           $id);
    $admin['permission_custom2']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM2,                           $id);
    $admin['permission_custom3']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM3,                           $id);
    $admin['permission_custom4']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM4,                           $id);
    $admin['permission_custom5']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM5,                           $id);
    $admin['permission_custom6']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM6,                           $id);
    $admin['permission_add_admins']       = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_ADMINS'),       $id);
    $admin['permission_delete_admins']    = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_ADMINS'),    $id);
    $admin['permission_edit_admins']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ADMINS'),      $id);
    $admin['permission_import_admins']    = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_ADMINS'),    $id);
    $admin['permission_list_admins']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_ADMINS'),      $id);
    $admin['permission_add_groups']       = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_GROUPS'),       $id);
    $admin['permission_delete_groups']    = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_GROUPS'),    $id);
    $admin['permission_edit_groups']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_GROUPS'),      $id);
    $admin['permission_import_groups']    = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_GROUPS'),    $id);
    $admin['permission_list_groups']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_GROUPS'),      $id);
    $admin['permission_add_mods']         = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_MODS'),         $id);
    $admin['permission_delete_mods']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_MODS'),      $id);
    $admin['permission_edit_mods']        = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_MODS'),        $id);
    $admin['permission_list_mods']        = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_MODS'),        $id);
    $admin['permission_add_servers']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_SERVERS'),      $id);
    $admin['permission_delete_servers']   = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_SERVERS'),   $id);
    $admin['permission_edit_servers']     = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_SERVERS'),     $id);
    $admin['permission_list_servers']     = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_SERVERS'),     $id);
    $admin['permission_add_bans']         = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_BANS'),         $id);
    $admin['permission_add_group_bans']   = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_GROUP_BANS'),   $id);
    $admin['permission_delete_bans']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_BANS'),      $id);
    $admin['permission_edit_all_bans']    = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ALL_BANS'),    $id);
    $admin['permission_edit_own_bans']    = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_OWN_BANS'),    $id);
    $admin['permission_edit_group_bans']  = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_GROUP_BANS'),  $id);
    $admin['permission_import_bans']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_BANS'),      $id);
    $admin['permission_unban_all_bans']   = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_UNBAN_ALL_BANS'),   $id);
    $admin['permission_unban_group_bans'] = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_UNBAN_GROUP_BANS'), $id);
    $admin['permission_unban_own_bans']   = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_UNBAN_OWN_BANS'),   $id);
    $admin['permission_ban_protests']     = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS'),     $id);
    $admin['permission_ban_submissions']  = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS'),  $id);
    $admin['permission_notify_prot']      = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_NOTIFY_PROT'),      $id);
    $admin['permission_notify_sub']       = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_NOTIFY_SUB'),       $id);
    $admin['permission_settings']         = $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_SETTINGS'),         $id);
  }
  
  $page->assign('permission_clear_actions', $userbank->HasAccess(array('ADMIN_OWNER')));
  $page->assign('permission_list_actions',  $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_ACTIONS')));
  $page->assign('permission_add_admins',    $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_ADMINS')));
  $page->assign('permission_delete_admins', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_DELETE_ADMINS')));
  $page->assign('permission_edit_admins',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ADMINS')));
  $page->assign('permission_import_admins', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_ADMINS')));
  $page->assign('permission_list_admins',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_LIST_ADMINS')));
  $page->assign('actions',                  $actions);
  $page->assign('admins',                   $admins);
  $page->assign('servers',                  $servers);
  $page->assign('server_admin_groups',      $server_admin_groups);
  $page->assign('web_admin_groups',         $web_admin_groups);
  $page->assign('end',                      $admins_end);
  $page->assign('start',                    $admins_start);
  $page->assign('total',                    $counts['admins']);
  $page->assign('total_pages',              $pages);
  $page->display('page_admin_admins');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>