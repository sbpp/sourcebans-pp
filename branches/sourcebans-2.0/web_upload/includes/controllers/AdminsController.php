<?php
class AdminsController extends BaseController
{
  protected function _title()
  {
    return ucwords($this->_registry->user->language->admins);
  }
  
  
  public function index() {}
  
  
  public function editdetails()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'EDIT_ADMINS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        if($_POST['password'] != $_POST['password_confirm'])
            throw new SBException($language->passwords_do_not_match);
        
        $admin           = SB_API::getAdmin($_POST['id']);
        $admin->auth     = $_POST['auth'];
        $admin->email    = $POST['email'];
        $admin->identity = ($_POST['auth'] == $this->_registry->steam_auth_type ? strtoupper($_POST['identity']) : $_POST['identity']);
        $admin->name     = $_POST['name'];
        $admin->password = $POST['password'];
        
        exit(json_encode(array(
          'redirect' => new SBUri('admin', 'admins')
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $user->permission_change_password = ($uri->id == $user->id || $user->hasAccess(array('OWNER')));
    
    $template->action_title = ucwords($language->edit_details);
    $template->admin        = SB_API::getAdmin($uri->id);
    $template->display('admin_admins_editdetails');
  }
  
  
  public function editgroups()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'EDIT_ADMINS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        $admin           = SB_API::getAdmin($_POST['id']);
        $admin->group_id = $_POST['web_group'];
        $admin->setServerGroups($_POST['srv_groups']);
        $admin->save();
        
        exit(json_encode(array(
          'redirect' => new SBUri('admin', 'admins'),
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage(),
        )));
      }
    }
    
    $template->action_title  = ucwords($language->edit_groups);
    $template->admin         = SB_API::getAdmin($uri->id);
    $template->server_groups = SB_API::getServerGroups();
    $template->web_groups    = SB_API::getWebGroups();
    $template->display('admin_admins_editgroups');
  }
}