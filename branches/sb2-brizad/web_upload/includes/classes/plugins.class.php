<?php
require_once READERS_DIR . 'plugins.php';
require_once WRITERS_DIR . 'plugins.php';

abstract class SBPlugin
{
  public $name;
  public $author;
  public $desc;
  public $version;
  public $url;

  /**
   * Basic plugin defaults if not strictly definded on in inherited class
   * 
   * @noreturn
   */
  public function setInfo($name = null, $author = null, $desc = null, $version = null, $url = null)
  {
    if (!empty($name)) $this->name = $name;
    if (!empty($author)) $this->author = $author;
    if (!empty($desc)) $this->desc = $desc;
    if (!empty($version)) $this->version = $version;
    if (!empty($url)) $this->url = $url;
  }
}

class SBPlugins
{
  /**
   * List of plugins
   */
  private static $plugins = array();
  
  
  /**
   * Calls a hook on the enabled plugins
   *
   * @param  string $hook     The hook to call
   * @param  mixed  $args[]   The arguments to pass to the hook
   * @return array  $ref_args The referenced arguments to pass back to the calling function
   */
  public static function call()
  {
    $args     = func_get_args();
    $hook     = array_shift($args);
    
    $ref_args = array();
    foreach($args as &$arg)
      $ref_args[] = &$arg;
    
    // Loop through all plugins and call the hook when it's enabled
    foreach(self::$plugins as $class => $plugin)
    {
      if(is_callable(array($class, $hook)) && $plugin['enabled'])
        call_user_func_array(array($class, $hook), $ref_args);
    }
    
    return $ref_args;
  }
  
  
  /**
   * Returns the list of plugins
   *
   * @return array The list of plugins
   */
  public static function getPlugins()
  {
    return self::$plugins;
  }
  
  
  /*
   * Initialize
   *
   * @noreturn
   */
  public static function init()
  {
    // Loop through all plugin files and include them
    foreach(glob(PLUGINS_DIR . '*.php') as $plugin)
    {
      $filename = pathinfo($plugin, PATHINFO_FILENAME);
      require_once $plugin;

      if (class_exists($filename) && method_exists($filename, '__construct'))
        SBPlugins::register(new $filename());
    }

    // Remove deleted plugins from database
    $plugins_reader = new PluginsReader();
    $plugins        = $plugins_reader->executeCached(ONE_DAY);
    
    foreach($plugins as $class => $enabled)
      if(!class_exists($class))
        PluginsWriter::delete($class);
    
    self::call('OnInit');
  }
  
  
  /*
   * Registers a plugin for use
   *
   * @param mixed  $plugin   The plugin object to register
   * @retrun bool  true/false if registration worked
   */
  public static function register($plugin)
  {
    if (is_object(!$plugin))
      return false;

    $class = get_class($plugin);

    if(!array_key_exists($class, self::$plugins))
      self::$plugins[$class] = array('name'    => $class->name,
                                     'author'  => $class->author,
                                     'desc'    => $class->desc,
                                     'version' => $class->version,
                                     'url'     => $class->url,
                                     'enabled' => false,
                                     'instance' => &$plugin );

    $plugins_reader = new PluginsReader();
    $plugins        = $plugins_reader->executeCached(ONE_DAY);

    if(isset($plugins[$class]))
      self::$plugins[$class]['enabled'] = $plugins[$class];
    else
      PluginsWriter::add($class);

    return true;
  }
}
