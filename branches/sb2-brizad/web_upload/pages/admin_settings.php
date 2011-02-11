<?php
require_once 'api.php';

$config   = SBConfig::getEnv('config');
$phrases  = SBConfig::getEnv('phrases');
$userbank = SBConfig::getEnv('userbank');
$page     = new Page($phrases['settings'], !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'SETTINGS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      switch($_POST['action'])
      {
        case 'settings':
          $settings = array('config.debug'          => isset($_POST['debug'])          ? 1 : 0,
                            'config.enableprotest'  => isset($_POST['enable_protest']) ? 1 : 0,
                            'email.smtp'            => isset($_POST['enable_smtp'])    ? 1 : 0,
                            'config.enablesubmit'   => isset($_POST['enable_submit'])  ? 1 : 0,
                            'config.exportpublic'   => isset($_POST['export_public'])  ? 1 : 0,
                            'banlist.hideadminname' => isset($_POST['hide_adminname']) ? 1 : 0,
                            'dash.lognopopup'       => isset($_POST['log_nopopup'])    ? 1 : 0,
                            'config.summertime'     => isset($_POST['summertime'])     ? 1 : 0);
          
          if(isset($_POST['bansperpage'])        && !empty($_POST['bansperpage'])        && is_numeric($_POST['bansperpage']))
            $settings['banlist.bansperpage']       = $_POST['bansperpage'];
          if(isset($_POST['dateformat'])         && !empty($_POST['dateformat'])         && is_string($_POST['dateformat']))
            $settings['config.dateformat']         = $_POST['dateformat'];
          if(isset($_POST['default_page'])       && !empty($_POST['default_page'])       && is_numeric($_POST['default_page']))
            $settings['config.defaultpage']        = $_POST['default_page'];
          if(isset($_POST['intro_text'])         && !empty($_POST['intro_text'])         && is_string($_POST['intro_text']))
            $settings['dash.intro.text']           = $_POST['intro_text'];
          if(isset($_POST['intro_title'])        && !empty($_POST['intro_title'])        && is_string($_POST['intro_title']))
            $settings['dash.intro.title']          = $_POST['intro_title'];
          if(isset($_POST['logo'])               && !empty($_POST['logo'])               && is_string($_POST['logo']))
            $settings['template.logo']             = $_POST['logo'];
          if(isset($_POST['password_minlength']) && !empty($_POST['password_minlength']) && is_numeric($_POST['password_minlength']))
            $settings['config.password.minlength'] = $_POST['password_minlength'];
          if(isset($_POST['smtp_host'])          && !empty($_POST['smtp_host'])          && is_string($_POST['smtp_host']))
            $settings['email.host']                = $_POST['smtp_host'];
          if(isset($_POST['smtp_password'])      && !empty($_POST['smtp_password'])      && is_string($_POST['smtp_password']))
            $settings['email.password']            = $_POST['smtp_password'];
          if(isset($_POST['smtp_port'])          && !empty($_POST['smtp_port'])          && is_string($_POST['smtp_port']))
            $settings['email.port']                = $_POST['smtp_port'];
          if(isset($_POST['smtp_secure'])        && is_string($_POST['smtp_secure']))
            $settings['email.secure']              = $_POST['smtp_secure'];
          if(isset($_POST['smtp_username'])      && !empty($_POST['smtp_username'])      && is_string($_POST['smtp_username']))
            $settings['email.username']            = $_POST['smtp_username'];
          if(isset($_POST['timezone'])           && !empty($_POST['timezone'])           && is_numeric($_POST['timezone']))
            $settings['config.timezone']           = $_POST['timezone'];
          if(isset($_POST['title'])              && !empty($_POST['title'])              && is_string($_POST['title']))
            $settings['template.title']            = $_POST['title'];
          
          SB_API::updateSettings($settings);
          break;
        case 'plugins':
          foreach($_POST['plugins'] as $plugin => $enabled)
          {
            if($enabled)
              PluginsWriter::enable($plugin);
            else
              PluginsWriter::disable($plugin);
          }
          
          break;
        default:
          throw new Exception($phrases['invalid_action']);
      }
      
      exit(json_encode(array(
        'redirect' => Util::buildQuery()
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $languages = array();
  $themes    = array();
  
  // Parse languages
  foreach(glob(LANGUAGES_DIR . '*.lang') as $language)
  {
    $code         = pathinfo(LANGUAGES_DIR . $language, PATHINFO_FILENAME);
    $translations = SB_API::getTranslations($code);
    
    $languages[]  = array('code' => $code,
                          'name' => $translations['info']['name']);
  }
  // Parse themes
  foreach(scandir(THEMES_DIR) as $theme)
  {
    $file = THEMES_DIR . $theme . '/theme.info';
    if(!file_exists($file))
      continue;
    
    $info     = parse_ini_file($file);
    $themes[] = array('dir'  => $theme,
                      'name' => $info['name']);
    
    if($theme != $config['config.theme'])
      continue;
    
    $theme_author  = $info['author'];
    $theme_link    = $info['link'];
    $theme_name    = $info['name'];
    $theme_version = $info['version'];
  }
  // Sort languages and themes by name
  Util::array_qsort($languages, 'name');
  Util::array_qsort($themes,    'name');
  
  $page->assign('permission_clear_logs', $userbank->HasAccess(array('OWNER')));
  $page->assign('config_debug',          $config['config.debug']);
  $page->assign('config_enableprotest',  $config['config.enableprotest']);
  $page->assign('config_enablesmtp',     $config['email.smtp']);
  $page->assign('config_enablesubmit',   $config['config.enablesubmit']);
  $page->assign('config_exportpublic',   $config['config.exportpublic']);
  $page->assign('config_hideadminname',  $config['banlist.hideadminname']);
  $page->assign('config_nopopup',        $config['dash.lognopopup']);
  $page->assign('config_summertime',     $config['config.summertime']);
  $page->assign('config_bansperpage',    $config['banlist.bansperpage']);
  $page->assign('config_dash_text',      $config['dash.intro.text']);
  $page->assign('config_dash_title',     $config['dash.intro.title']);
  $page->assign('config_dateformat',     $config['config.dateformat']);
  $page->assign('config_language',       $config['config.language']);
  $page->assign('config_logo',           $config['template.logo']);
  $page->assign('config_min_password',   $config['config.password.minlength']);
  $page->assign('config_smtp_host',      $config['email.host']);
  $page->assign('config_smtp_password',  $config['email.password']);
  $page->assign('config_smtp_port',      $config['email.port']);
  $page->assign('config_smtp_secure',    $config['email.secure']);
  $page->assign('config_smtp_username',  $config['email.username']);
  $page->assign('config_timezone',       $config['config.timezone']);
  $page->assign('config_title',          $config['template.title']);
  $page->assign('admins',                $admins['list']);
  $page->assign('languages',             $languages);
  $page->assign('logs',                  $logs['list']);
  $page->assign('plugins',               SB_API::getPlugins());
  $page->assign('themes',                $themes);
  $page->assign('theme_author',          $theme_author);
  $page->assign('theme_link',            $theme_link);
  $page->assign('theme_name',            $theme_name);
  $page->assign('theme_version',         $theme_version);
  $page->assign('total_pages',           $pages);
  $page->display('page_admin_settings');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>