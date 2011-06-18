<?php
class SubmitbanController extends BaseController
{
  public function index()
  {
    $language = $this->_registry->user->language;
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    
    if(!$settings->enable_submit)
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        $submission            = SB_API::createSubmission();
        $submission->ip        = $_POST['ip'];
        $submission->name      = $_POST['name'];
        $submission->reason    = $_POST['reason'];
        $submission->server_id = $_POST['server'];
        $submission->steam     = strtoupper($_POST['steam']);
        $submission->subname   = $_POST['subname'];
        $submission->subemail  = $_POST['subemail'];
        $submission->save();
        
        // If one or more demos were uploaded, add them
        foreach($_FILES['demo'] as $file)
        {
          $demo           = SB_API::createDemo();
          $demo->ban_id   = $submission->id;
          $demo->name     = $file['name'];
          $demo->tmp_name = $file['tmp_name'];
          $demo->type     = $this->_registry->submission_type;
          $demo->save();
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
    
    $template->action_title = ucwords($language->submit_ban);
    $template->servers      = $this->_registry->servers;
    $template->display('submitban');
  }
}