<?php
/**
 * This file will create the basic structure that all sourcebans pages will be made from
 * @package SourceBans
 * @subpackage Template
 */
class Page extends Smarty
{
  private $page_title;
  
  /**
   * This is an array of panes to add to this page
   *
   * @var array List of panes to add to this page
   */
  private $panes = array();
  
  /**
   * This is an array of JS files to include into the header of our page
   *
   * @var array List of relative JS files in include
   */
  private $scripts = array();
  
  /**
   * This is an array of CSS files to include into the HTML header of our page
   *
   * @var array List of relative CSS files to include in our header
   */
  private $styles = array();
  
  /**
   * This is an array of tabs to add to this page
   *
   * @var array List of tabs to add to this page
   */
  private $tabs = array();
  
  /**
   * This sets the active main menu tab on the page
   *
   * @var integer This value starts at 0 for the Home tab
   */
  private $menu_cat = 0;
  
  /**
   * This sets the active sub-nav button of the page
   *
   * @var integer This value starts at 0 for the first element, but -1 will not make any buttons active
   */
  private $menu_itm = -1;
  
  /**
   * If this is set to true (default) the full menu, side menu's etc will be displayed
   * If false, it will only display the template file specified.
   *
   * @var boolean
   */
  private $full_page = true;
  
  /**
   * This will hold the params for our page message
   *
   * @var array
   */
  private $page_message = array();
  
  
  function __construct($title = '', $full_page = true)
  {
    $config                = Env::get('config');
    $userbank              = Env::get('userbank');
    
    $this->compile_dir     = BASE_PATH  . 'themes_c';
    $this->debugging       = (defined('DEBUG_MODE') && DEBUG_MODE) || $config['config.debug'];
    $this->error_reporting = E_ALL & ~E_NOTICE;
    $this->full_page       = $full_page;
    $this->page_title      = $title;
    $this->template_dir    = THEMES_DIR . ($userbank->is_logged_in() ? $userbank->GetProperty('theme') : $config['config.theme']);
  }
  
  /**
   * This adds a pane to this page
   *
   * @param string $id   The unique id of the pane
   * @param string $html The HTML of the pane
   */
  public function addPane($id, $html)
  {
    $this->panes[] = array('id'   => $id,
                           'html' => $html);
  }
  
  /**
   * This allows us to set the JS files that we want to include on our page
   *
   * @param array $js an array of JS file locations to include
   */
  public function addScript($script)
  {
    if(!in_array($script, $this->scripts))
      $this->scripts[] = $script;
  }
  
  /**
   * This allows us to set the css files that we want to include on our page
   *
   * @param array $css_f an array of CSS file locations to include
   */
  public function addStyle($style)
  {
    if(!in_array($style, $this->styles))
      $this->styles[]  = $style;
  }
  
  /**
   * This adds a tab to this page
   *
   * @param string $id   The unique id of the tab
   * @param string $name The name of the tab
   * @param string $desc The description of the tab
   */
  public function addTab($id, $url, $name)
  {
    $this->tabs[]  = array('id'   => $id,
                           'url'  => $url,
                           'name' => $name);
  }
  
  /**
   * This will assign a value to the current page using the parent superclass
   *
   * @param string $key The key of the value to assign
   * @param mixed $var The value to assign
   */
  public function assign($key, $var)
  {
    parent::assign($key, $var);
  }
  
  /**
   * This is the main function that will build the page using the class functions
   *
   * @param string $file This is the template file that we want to include
   */
  public function display($file)
  {
    $config     = Env::get('config');
    $phrases    = Env::get('phrases');
    $quotes     = Env::get('quotes');
    $userbank   = Env::get('userbank');
    $quote      = $quotes[array_rand($quotes)];
    
    list($page) = SBPlugins::call('OnDisplayPage', $this, $file);
    
    foreach(get_object_vars($page) as $key => $value)
      $this->$key = $value;
    
    // Assign global variables
    parent::assign('active',                   Env::get('active'));
    parent::assign('admin_panes',              $this->panes);
    parent::assign('admin_tabs',               $this->tabs);
    parent::assign('date_format',              $config['config.dateformat']);
    parent::assign('enable_protest',           $config['config.enableprotest']);
    parent::assign('enable_submit',            $config['config.enablesubmit']);
    parent::assign('logged_in',                $userbank->is_logged_in());
    parent::assign('page_title',               $this->page_title);
    parent::assign('quote_name',               $quote['name']);
    parent::assign('quote_text',               $quote['text']);
    parent::assign('SB_VERSION',               SB_VERSION);
    parent::assign('scripts',                  $this->scripts);
    parent::assign('styles',                   $this->styles);
    parent::assign('tabs',                     Tabs::getTabs());
    parent::assign('theme_dir',                $config['config.theme']);
    parent::assign('user_permission_admins',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_ADMINS',  'ADMIN_DELETE_ADMINS',  'ADMIN_EDIT_ADMINS',     'ADMIN_LIST_ADMINS')));
    parent::assign('user_permission_bans',     $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_BANS',    'ADMIN_EDIT_ALL_BANS',  'ADMIN_EDIT_GROUP_BANS', 'ADMIN_EDIT_OWN_BANS', 'ADMIN_LIST_BANS', 'ADMIN_BAN_PROTESTS', 'ADMIN_BAN_SUBMISSIONS')));
    parent::assign('user_permission_groups',   $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_GROUPS',  'ADMIN_DELETE_GROUPS',  'ADMIN_EDIT_GROUPS',     'ADMIN_LIST_GROUPS')));
    parent::assign('user_permission_mods',     $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_MODS',    'ADMIN_DELETE_MODS',    'ADMIN_EDIT_MODS',       'ADMIN_LIST_MODS')));
    parent::assign('user_permission_servers',  $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_SERVERS', 'ADMIN_DELETE_SERVERS', 'ADMIN_EDIT_SERVERS',    'ADMIN_LIST_SERVERS')));
    parent::assign('user_permission_settings', $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_SETTINGS')));
    parent::assign('username',                 $userbank->GetProperty('name'));
    
    // Assign language phrases
    foreach($phrases as $name => $value)
      parent::assign('lang_' . $name, $value);
    
    if($this->full_page)
      parent::display('header.tpl');
    parent::display($file . '.tpl');
    if($this->full_page)
      parent::display('footer.tpl');
  }
  
  /**
   * This function will add a messagebox to the top of our page to display errors and such
   *
   * @param string $title The title to show in the message box
   * @param string $message The content of the message box
   * @param string $class The css class of the message box
   */
  public function setPageMessage($title, $message, $class)
  {
    $this->page_message = array(
      'class'   => $class,
      'message' => $message,
      'title'   => $title
    );
  }
}
?>