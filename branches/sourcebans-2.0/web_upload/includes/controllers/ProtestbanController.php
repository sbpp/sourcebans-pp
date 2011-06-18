<?php
class ProtestbanController extends BaseController
{
  public function index()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    
    if(!$settings->enable_protest)
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        $protest         = SB_API::createProtest();
        $protest->email  = $_POST['email'];
        $protest->ip     = $_POST['ip'];
        $protest->name   = $_POST['name'];
        $protest->reason = $_POST['reason'];
        $protest->steam  = strtoupper($_POST['steam']);
        $protest->type   = $_POST['type'];
        $protest->save();
        
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
    
    $template->action_title = ucwords($language->protest_ban);
    $template->display('protestban');
  }
}