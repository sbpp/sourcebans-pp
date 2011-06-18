<?php
class LoginController extends BaseController
{
  public function index()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $user     = $this->_registry->user;
    
    // TODO: Uncomment after fixing hasAccess
    //if($user->is_admin())
    //  Util::redirect(new SBUri('admin'));
    if($user->is_logged_in())
      Util::redirect(new SBUri('account'));
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        if(!$user->login($_POST['username'], $_POST['password'], isset($_POST['remember'])))
          throw new SBException($language->invalid_login);
        
        exit(json_encode(array(
          'redirect' => new SBUri($user->is_admin() ? 'admin' : 'account')
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $template->action_title = $language->login;
    $template->display('login');
  }
}