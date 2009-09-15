<?php
require_once 'init.php';
require_once READERS_DIR . 'admins.php';

$db       = Env::get('db');
$config   = Env::get('config');
$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page('Lost Password');

try
{
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      $admins_reader = new AdminsReader();
      $admins        = $admins_reader->executeCached(ONE_MINUTE * 5);
      
      foreach($admins as $id => $admin)
      {
        if($admin['email'] != $_POST['email'])
          continue;
        
        $validation = md5(time());
        
        $db->Execute('UPDATE ' . Env::get('prefix') . '_admins
                      SET    validate = ?
                      WHERE  email    = ?',
                      array($validation, $_POST['email']));
        
        Util::mail($_POST['email'], 'noreply@' . $_SERVER['HTTP_HOST'], 'SourceBans Password Reset',
                   'Hello ' . $admin['name'] . ',\n\n' .
                   'You have requested to have your password reset for your SourceBans account.\n' .
                   'To complete this process, please click the following link.\n' .
                   'NOTE: If you didn\'t request this reset, then simply ignore this email.\n\n' .
                   'http://' . dirname($_SERVER['SCRIPT_NAME']) . '/lostpassword.php?email=' . $_POST['email'] . '&validation=' . $validation);
        
        break;
      }
      
      exit(json_encode(array(
        'redirect' => Env::get('active')
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  if(isset($_GET['email'], $_GET['validation']) && !empty($_GET['email']) && !empty($_GET['validation']))
  {
    $admins_reader = new AdminsReader();
    $admins        = $admins_reader->executeCached(ONE_MINUTE * 5);
    
    foreach($admins as $id => $admin)
    {
      if($admin['email'] != $_GET['email'] || $admin['validate'] != $_GET['validation'])
        continue;
      
      $password = Util::generate_salt($config['config.password.minlength'] + 1);
      
      $db->Execute('UPDATE ' . Env::get('prefix') . '_admins
                    SET    password = ?,
                           validate = NULL
                    WHERE  email    = ?',
                    array(CUserManager::encrypt_password($password), $_POST['email']));
      
      $page->assign('password', $password);
      
      break;
    }
  }
  
  $page->display('page_lostpassword');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>