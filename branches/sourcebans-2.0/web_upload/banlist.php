<?php
require_once 'api.php';

$config   = Env::get('config');
$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page(ucwords($phrases['ban_list']), !isset($_GET['nofullpage']));

try
{
  $limit      = $config['banlist.bansperpage'];
  $pagenr     = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 1 ? $_GET['page'] : 1;
  
  $order      = isset($_GET['order']) && is_string($_GET['order']) ? $_GET['order'] : 'desc';
  $sort       = isset($_GET['sort'])  && is_string($_GET['sort'])  ? $_GET['sort']  : 'time';
  
  $admins     = SB_API::getAdmins();
  $bans       = SB_API::getBans(isset($_GET['hideinactive']), $limit, $pagenr, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC,
                                isset($_GET['search']) ? $_GET['search'] : null, isset($_GET['type']) ? $_GET['type'] : null);
  
  $bans_start = ($pagenr - 1)       * $limit;
  $bans_end   = $bans_start         + $limit;
  $pages      = ceil($bans['count'] / $limit);
  if($bans_end > $bans['count'])
    $bans_end = $bans['count'];
  
  $page->assign('permission_add_bans',         $userbank->HasAccess(array('OWNER', 'ADD_BANS')));
  $page->assign('permission_bans',             $userbank->HasAccess(array('OWNER', 'DELETE_BANS', 'EDIT_ALL_BANS', 'EDIT_GROUP_BANS', 'EDIT_OWN_BANS', 'UNBAN_ALL_BANS', 'UNBAN_GROUP_BANS', 'UNBAN_OWN_BANS')));
  $page->assign('permission_delete_bans',      $userbank->HasAccess(array('OWNER', 'DELETE_BANS')));
  $page->assign('permission_edit_all_bans',    $userbank->HasAccess(array('OWNER', 'EDIT_ALL_BANS')));
  $page->assign('permission_edit_group_bans',  $userbank->HasAccess(array('OWNER', 'EDIT_GROUP_BANS')));
  $page->assign('permission_edit_own_bans',    $userbank->HasAccess(array('OWNER', 'EDIT_OWN_BANS')));
  $page->assign('permission_export_bans',      $userbank->HasAccess(array('OWNER')) || $config['config.exportpublic']);
  $page->assign('permission_list_admins',      $userbank->HasAccess(array('OWNER', 'LIST_ADMINS')));
  $page->assign('permission_unban_all_bans',   $userbank->HasAccess(array('OWNER', 'UNBAN_ALL_BANS')));
  $page->assign('permission_unban_group_bans', $userbank->HasAccess(array('OWNER', 'UNBAN_GROUP_BANS')));
  $page->assign('permission_unban_own_bans',   $userbank->HasAccess(array('OWNER', 'UNBAN_OWN_BANS')));
  $page->assign('permission_edit_comments',    $userbank->HasAccess(array('OWNER')));
  $page->assign('permission_list_comments',    $userbank->is_admin());
  $page->assign('hide_adminname',              $config['banlist.hideadminname']);
  $page->assign('admins',                      $admins['list']);
  $page->assign('bans',                        $bans['list']);
  $page->assign('servers',                     SB_API::getServers());
  $page->assign('end',                         $bans_end);
  $page->assign('order',                       $order);
  $page->assign('sort',                        $sort);
  $page->assign('start',                       $bans_start);
  $page->assign('total',                       $bans['count']);
  $page->assign('total_pages',                 $pages);
  $page->display('page_banlist');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>