<?php
require_once 'api.php';

$config   = SBConfig::getEnv('config');
$phrases  = SBConfig::getEnv('phrases');
$userbank = SBConfig::getEnv('userbank');
$page     = new Page($phrases['admins'], !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'ADD_ADMINS', 'DELETE_ADMINS', 'EDIT_ADMINS', 'LIST_ADMINS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      switch($_POST['action'])
      {
        case 'add':
          if(!$userbank->HasAccess(array('OWNER', 'ADD_ADMINS')))
            throw new Exception($phrases['access_denied']);
          if($_POST['password'] != $_POST['password_confirm'])
            throw new Exception($phrases['passwords_do_not_match']);
          
          SB_API::addAdmin($_POST['name'], $_POST['auth'], $_POST['auth'] == STEAM_AUTH_TYPE ? strtoupper($_POST['identity']) : $_POST['identity'], $_POST['email'],
                           $_POST['password'], isset($_POST['srv_password']) ? $_POST['password'] : null, $_POST['srv_groups'], $_POST['web_group']);
          break;
        case 'import':
          if(!$userbank->HasAccess(array('OWNER', 'IMPORT_ADMINS')))
            throw new Exception($phrases['access_denied']);
          
          SB_API::importAdmins($_FILES['file']['name'], $_FILES['file']['tmp_name']);
          break;
        default:
          throw new Exception($phrases['invalid_action']);
      }
      
      exit(json_encode(array(
        'redirect' => util::buildQuery()
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $limit        = 25;
  $pagenr       = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 1 ? $_GET['page'] : 1;
  
  $order        = isset($_GET['order']) && is_string($_GET['order']) ? $_GET['order'] : 'asc';
  $sort         = isset($_GET['sort'])  && is_string($_GET['sort'])  ? $_GET['sort']  : 'name';
  
  $actions      = SB_API::getActions($limit, $pagenr, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC,
                                     isset($_GET['search']) ? $_GET['search'] : null, isset($_GET['type']) ? $_GET['type'] : null);
  $admins       = SB_API::getAdmins($limit,  $pagenr, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC,
                                    isset($_GET['search']) ? $_GET['search'] : null, isset($_GET['type']) ? $_GET['type'] : null);
  
  $admins_start = ($pagenr - 1)         * $limit;
  $admins_end   = $admins_start         + $limit;
  $pages        = ceil($admins['count'] / $limit);
  if($admins_end > $admins['count'])
    $admins_end = $admins['count'];
  
  foreach($admins['list'] as $id => &$admin)
  {
    $admin['permission_reservation']      = $userbank->HasAccess(SM_ROOT . SM_RESERVATION,           $id);
    $admin['permission_generic']          = $userbank->HasAccess(SM_ROOT . SM_GENERIC,               $id);
    $admin['permission_kick']             = $userbank->HasAccess(SM_ROOT . SM_KICK,                  $id);
    $admin['permission_ban']              = $userbank->HasAccess(SM_ROOT . SM_BAN,                   $id);
    $admin['permission_unban']            = $userbank->HasAccess(SM_ROOT . SM_UNBAN,                 $id);
    $admin['permission_slay']             = $userbank->HasAccess(SM_ROOT . SM_SLAY,                  $id);
    $admin['permission_changemap']        = $userbank->HasAccess(SM_ROOT . SM_CHANGEMAP,             $id);
    $admin['permission_cvar']             = $userbank->HasAccess(SM_ROOT . SM_CVAR,                  $id);
    $admin['permission_config']           = $userbank->HasAccess(SM_ROOT . SM_CONFIG,                $id);
    $admin['permission_chat']             = $userbank->HasAccess(SM_ROOT . SM_CHAT,                  $id);
    $admin['permission_vote']             = $userbank->HasAccess(SM_ROOT . SM_VOTE,                  $id);
    $admin['permission_password']         = $userbank->HasAccess(SM_ROOT . SM_PASSWORD,              $id);
    $admin['permission_rcon']             = $userbank->HasAccess(SM_ROOT . SM_RCON,                  $id);
    $admin['permission_cheats']           = $userbank->HasAccess(SM_ROOT . SM_CHEATS,                $id);
    $admin['permission_custom1']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM1,               $id);
    $admin['permission_custom2']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM2,               $id);
    $admin['permission_custom3']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM3,               $id);
    $admin['permission_custom4']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM4,               $id);
    $admin['permission_custom5']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM5,               $id);
    $admin['permission_custom6']          = $userbank->HasAccess(SM_ROOT . SM_CUSTOM6,               $id);
    $admin['permission_add_admins']       = $userbank->HasAccess(array('OWNER', 'ADD_ADMINS'),       $id);
    $admin['permission_delete_admins']    = $userbank->HasAccess(array('OWNER', 'DELETE_ADMINS'),    $id);
    $admin['permission_edit_admins']      = $userbank->HasAccess(array('OWNER', 'EDIT_ADMINS'),      $id);
    $admin['permission_import_admins']    = $userbank->HasAccess(array('OWNER', 'IMPORT_ADMINS'),    $id);
    $admin['permission_list_admins']      = $userbank->HasAccess(array('OWNER', 'LIST_ADMINS'),      $id);
    $admin['permission_add_groups']       = $userbank->HasAccess(array('OWNER', 'ADD_GROUPS'),       $id);
    $admin['permission_delete_groups']    = $userbank->HasAccess(array('OWNER', 'DELETE_GROUPS'),    $id);
    $admin['permission_edit_groups']      = $userbank->HasAccess(array('OWNER', 'EDIT_GROUPS'),      $id);
    $admin['permission_import_groups']    = $userbank->HasAccess(array('OWNER', 'IMPORT_GROUPS'),    $id);
    $admin['permission_list_groups']      = $userbank->HasAccess(array('OWNER', 'LIST_GROUPS'),      $id);
    $admin['permission_add_mods']         = $userbank->HasAccess(array('OWNER', 'ADD_MODS'),         $id);
    $admin['permission_delete_mods']      = $userbank->HasAccess(array('OWNER', 'DELETE_MODS'),      $id);
    $admin['permission_edit_mods']        = $userbank->HasAccess(array('OWNER', 'EDIT_MODS'),        $id);
    $admin['permission_list_mods']        = $userbank->HasAccess(array('OWNER', 'LIST_MODS'),        $id);
    $admin['permission_add_servers']      = $userbank->HasAccess(array('OWNER', 'ADD_SERVERS'),      $id);
    $admin['permission_delete_servers']   = $userbank->HasAccess(array('OWNER', 'DELETE_SERVERS'),   $id);
    $admin['permission_edit_servers']     = $userbank->HasAccess(array('OWNER', 'EDIT_SERVERS'),     $id);
    $admin['permission_list_servers']     = $userbank->HasAccess(array('OWNER', 'LIST_SERVERS'),     $id);
    $admin['permission_add_bans']         = $userbank->HasAccess(array('OWNER', 'ADD_BANS'),         $id);
    $admin['permission_add_group_bans']   = $userbank->HasAccess(array('OWNER', 'ADD_GROUP_BANS'),   $id);
    $admin['permission_delete_bans']      = $userbank->HasAccess(array('OWNER', 'DELETE_BANS'),      $id);
    $admin['permission_edit_all_bans']    = $userbank->HasAccess(array('OWNER', 'EDIT_ALL_BANS'),    $id);
    $admin['permission_edit_own_bans']    = $userbank->HasAccess(array('OWNER', 'EDIT_OWN_BANS'),    $id);
    $admin['permission_edit_group_bans']  = $userbank->HasAccess(array('OWNER', 'EDIT_GROUP_BANS'),  $id);
    $admin['permission_import_bans']      = $userbank->HasAccess(array('OWNER', 'IMPORT_BANS'),      $id);
    $admin['permission_unban_all_bans']   = $userbank->HasAccess(array('OWNER', 'UNBAN_ALL_BANS'),   $id);
    $admin['permission_unban_group_bans'] = $userbank->HasAccess(array('OWNER', 'UNBAN_GROUP_BANS'), $id);
    $admin['permission_unban_own_bans']   = $userbank->HasAccess(array('OWNER', 'UNBAN_OWN_BANS'),   $id);
    $admin['permission_ban_protests']     = $userbank->HasAccess(array('OWNER', 'BAN_PROTESTS'),     $id);
    $admin['permission_ban_submissions']  = $userbank->HasAccess(array('OWNER', 'BAN_SUBMISSIONS'),  $id);
    $admin['permission_notify_prot']      = $userbank->HasAccess(array('OWNER', 'NOTIFY_PROT'),      $id);
    $admin['permission_notify_sub']       = $userbank->HasAccess(array('OWNER', 'NOTIFY_SUB'),       $id);
    $admin['permission_settings']         = $userbank->HasAccess(array('OWNER', 'SETTINGS'),         $id);
  }
  
  $page->assign('permission_clear_actions',  $userbank->HasAccess(array('OWNER')));
  $page->assign('permission_list_actions',   $userbank->HasAccess(array('OWNER', 'LIST_ACTIONS')));
  $page->assign('permission_add_admins',     $userbank->HasAccess(array('OWNER', 'ADD_ADMINS')));
  $page->assign('permission_delete_admins',  $userbank->HasAccess(array('OWNER', 'DELETE_ADMINS')));
  $page->assign('permission_edit_admins',    $userbank->HasAccess(array('OWNER', 'EDIT_ADMINS')));
  $page->assign('permission_import_admins',  $userbank->HasAccess(array('OWNER', 'IMPORT_ADMINS')));
  $page->assign('permission_list_admins',    $userbank->HasAccess(array('OWNER', 'LIST_ADMINS')));
  $page->assign('permission_list_overrides', $userbank->HasAccess(array('OWNER', 'LIST_OVERRIDES')));
  $page->assign('actions',                   $actions['list']);
  $page->assign('admins',                    $admins['list']);
  $page->assign('overrides',                 SB_API::getOverrides());
  $page->assign('servers',                   SB_API::getServers());
  $page->assign('server_groups',             SB_API::getGroups(SERVER_GROUPS));
  $page->assign('web_groups',                SB_API::getGroups(WEB_GROUPS));
  $page->assign('end',                       $admins_end);
  $page->assign('order',                     $order);
  $page->assign('sort',                      $sort);
  $page->assign('start',                     $admins_start);
  $page->assign('total',                     $admins['count']);
  $page->assign('total_pages',               $pages);
  $page->display('page_admin_admins');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>