<?php
require_once READERS_DIR . 'plugins.php';
require_once WRITERS_DIR . 'plugins.php';

abstract class SBPlugin
{
  /*
   * Registers the plugin for use
   *
   * @param string $name    The name of the plugin
   * @param string $author  The author of the plugin
   * @param string $desc    The description of the plugin
   * @param float  $version The version number of the plugin
   * @param string $url     The URL of the plugin
   */
  public function __construct($name, $author, $desc, $version, $url)
  {
    SBPlugins::register(get_class($this), $name, $author, $desc, $version, $url);
  }
}

class SBPlugins
{
  /*
   * List of plugins
   */
  private static $plugins = array();
  
  
  /*
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
  
  
  /*
   * Returns the list of plugins
   */
  public static function getPlugins()
  {
    return self::$plugins;
  }
  
  
  /*
   * Initialize 
   */
  public static function init()
  {
    // Loop through all plugin files and include them
    foreach(glob(PLUGINS_DIR . '*.php') as $plugin)
      require_once $plugin;
    
    // Remove deleted plugins from database
    $plugins_writer = new PluginsWriter();
    $plugins_reader = new PluginsReader();
    $pluginsInDB    = $plugins_reader->executeCached(ONE_DAY);
    foreach($pluginsInDB as $class => $enabled)
      if(!class_exists($class))
        $plugins_writer->delete($class);
    
    self::call('OnInit');
  }
  
  
  /*
   * Registers a plugin for use
   *
   * @param string $class   The class name of the plugin to register
   * @param string $name    The name of the plugin
   * @param string $author  The author of the plugin
   * @param string $desc    The description of the plugin
   * @param string $version The version number of the plugin
   * @param string $url     The URL of the plugin
   */
  public static function register($class, $name, $author, $desc, $version, $url)
  {
    if(!array_key_exists($class, self::$plugins))
      self::$plugins[$class] = array('name'    => $name,
                                     'author'  => $author,
                                     'desc'    => $desc,
                                     'version' => $version,
                                     'url'     => $url,
                                     'enabled' => true);
    
    $plugins_reader = new PluginsReader();
    $plugins        = $plugins_reader->executeCached(ONE_DAY);
    
    if(isset($plugins[$class]))
      self::$plugins[$class]['enabled'] = $plugins[$class];
    else
      PluginsWriter::add($class);
  }
}
?>