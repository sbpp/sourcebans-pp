<?php
class LostpasswordController extends BaseController
{
  public function index()
  {
    $settings = $this->_registry->settings;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        $admins = $this->_registry->admins;
        foreach($admins as $admin)
        {
          if($admin->email != $_POST['email'])
            continue;
          
          $validation = md5(time());
          
          $mail =
            Util::mail($_POST['email'], 'noreply@' . $_SERVER['HTTP_HOST'], 'SourceBans Password Reset',
              'Hello ' . $admin->name . ',\n\n' .
              'You have requested to have your password reset for your SourceBans account.\n' .
              'To complete this process, please click the following link.\n\n' .
              new SBUri('lostpassword', null,
               array('email' => $_POST['email'], 'validation' => $validation)) . '\n\n' .
              'NOTE: If you didn\'t request this reset, then simply ignore this email.'));
          if($mail !== true)
            throw new SBException('Failed to send e-mail: ' . $mail);
          
          $admin->validate = $validation;
          $admin->save();
          break;
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
    if(isset($uri->email, $uri->validation) && !empty($uri->email) && !empty($uri->validation))
    {
      $admins = $this->_registry->admins;
      foreach($admins as $admin)
      {
        if($admin->email != $uri->email || $admin->validate != $uri->validation)
          continue;
        
        $admin->password = $admins->encrypt_password(
          Util::generate_password($settings->password_min_length + 1));
        $admin->validate = null;
        $admin->save();
        
        $template->password = $password;
        break;
      }
    }
    
    // TODO: Translate "Lost Password"
    $template->action_title = 'Lost Password';
    $template->display('lostpassword');
  }
}