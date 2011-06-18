<?php
class BansController extends BaseController
{
  protected function _title()
  {
    return ucwords($this->_registry->user->language->bans);
  }
  
  
  public function index()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    $limit    = $settings->items_per_page;
    $page     = isset($uri->page) && is_numeric($uri->page) && $uri->page > 1 ? $uri->page : 1;
    
    $order    = isset($uri->order) ? $uri->order : 'desc';
    $sort     = isset($uri->sort)  ? $uri->sort  : 'insert_time';
    
    $admins   = $this->_registry->admins;
    /*$bans     = $this->_registry->bans->get(isset($uri->hideinactive), $limit, $pagenr, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC,
                                isset($uri->search) ? $uri->search : null, isset($uri->type) ? $uri->type : null);*/
    $bans     = $this->_registry->bans;
    $servers  = $this->_registry->servers;
    
    //Util::object_sort($bans, 'insert_time', SORT_DESC);
    //$bans     = array_slice($bans, $page * $limit - $limit, $limit);
    $bans->limit  = $limit;
    $bans->offset = $page * $limit - $limit;
    
    $count      = count($bans);
    $bans_start = ($page - 1) * $limit;
    $bans_end   = $bans_start + $limit;
    $pages      = ceil($count / $limit);
    if($bans_end > $count)
      $bans_end = $count;
    
    $user->permission_add_bans         = $user->hasAccess(array('OWNER', 'ADD_BANS'));
    $user->permission_bans             = $user->hasAccess(array('OWNER', 'DELETE_BANS', 'EDIT_ALL_BANS', 'EDIT_GROUP_BANS', 'EDIT_OWN_BANS', 'UNBAN_ALL_BANS', 'UNBAN_GROUP_BANS', 'UNBAN_OWN_BANS'));
    $user->permission_delete_bans      = $user->hasAccess(array('OWNER', 'DELETE_BANS'));
    $user->permission_edit_all_bans    = $user->hasAccess(array('OWNER', 'EDIT_ALL_BANS'));
    $user->permission_edit_group_bans  = $user->hasAccess(array('OWNER', 'EDIT_GROUP_BANS'));
    $user->permission_edit_own_bans    = $user->hasAccess(array('OWNER', 'EDIT_OWN_BANS'));
    $user->permission_export_bans      = $user->hasAccess(array('OWNER')) || $settings->bans_public_export;
    $user->permission_list_admins      = $user->hasAccess(array('OWNER', 'LIST_ADMINS'));
    $user->permission_unban_all_bans   = $user->hasAccess(array('OWNER', 'UNBAN_ALL_BANS'));
    $user->permission_unban_group_bans = $user->hasAccess(array('OWNER', 'UNBAN_GROUP_BANS'));
    $user->permission_unban_own_bans   = $user->hasAccess(array('OWNER', 'UNBAN_OWN_BANS'));
    $user->permission_edit_comments    = $user->hasAccess(array('OWNER'));
    $user->permission_list_comments    = $user->is_admin();
    
    $template->admins      = $admins;
    $template->bans        = $bans;
    $template->servers     = $servers;
    $template->end         = $bans_end;
    $template->order       = $order;
    $template->sort        = $sort;
    $template->start       = $bans_start;
    $template->total       = $count;
    $template->total_pages = $pages;
    $template->display('bans');
  }
  
  
  public function edit()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'EDIT_ALL_BANS', 'EDIT_GROUP_BANS', 'EDIT_OWN_BANS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        $ban         = SB_API::getBan($_POST['id']);
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
          $demo->ban_id   = $_POST['id'];
          $demo->name     = $file['name'];
          $demo->tmp_name = $file['tmp_name'];
          $demo->type     = $this->_registry->ban_type;
          $demo->save();
        }
        
        exit(json_encode(array(
          'redirect' => new SBUri('admin', 'bans')
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $template->action_title = ucwords($language->edit_ban);
    $template->ban          = SB_API::getBan($uri->id);
    $template->display('admin_bans_edit');
  }
  
  
  public function email()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'BAN_PROTESTS', 'BAN_SUBMISSIONS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        SB_API::mail($_POST['email'], 'noreply@' . $_SERVER['HTTP_HOST'], $_POST['subject'], $_POST['message']);
        
        exit(json_encode(array(
          'redirect' => new SBUri('admin', 'bans')
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $template->display('admin_bans_email');
  }
  
  
  public function export()
  {
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $language = $this->_registry->user->language;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER')) && !$settings->bans_public_export)
      throw new SBException($language->access_denied);
    
    $type = isset($uri->type) && is_numeric($uri->type) ? $uri->type : $this->_registry->steam_ban_type;
    
    $bans = SB_API::getBans(true, 0, 1, null, null, 0, 'length');
    
    header('Content-Type: application/x-httpd-php php');
    header('Content-Disposition: attachment; filename="banned_' . ($type == $this->_registry->ip_ban_type ? 'ip' : 'user') . '.cfg"');
    
    foreach($bans as $ban)
    {
      if($ban->type != $type)
        continue;
      
      if($type      == $this->_registry->steam_ban_type)
        echo 'banid 0 ' . $ban->steam . "\t\t\t// " . $ban->name . "\n";
      else if($type == $this->_registry->ip_ban_type)
        echo 'banip 0 ' . $ban->ip    . "\t\t\t// " . $ban->name . "\n";
    }
  }
}