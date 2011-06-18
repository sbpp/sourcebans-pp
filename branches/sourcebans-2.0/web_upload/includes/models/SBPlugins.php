<?php
/**
 * SourceBans plugins model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Plugins
 * @version    $Id$
 */
class SBPlugins extends BaseTableModel
{
  protected $_sort = 'name';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'plugins';
  }
  
  /**
   * Calls a hook on the enabled plugins
   *
   * @param  string $name      The hook to call
   * @param  array  $arguments The arguments to pass to the hook
   * @return array             The return value and referenced arguments to pass back to the calling function
   */
  public function __call($name, $arguments)
  {
    $this->_fetch();
    
    // Build an array with argument references
    // to pass to the hooks to allow modification
    // as $arguments is by value
    $ref_args = array();
    foreach($arguments as &$arg)
    {
      $ref_args[] = &$arg;
    }
    
    // Loop through all plugins and call the hook
    $ret = null;
    foreach($this->_data as $plugin)
    {
      if(!$plugin->enabled || !is_callable(array($plugin, $name)))
        continue;
      
      $ret = call_user_func_array(array($plugin, $name), $ref_args);
    }
    
    return array_merge(array($ret), $ref_args);
  }
  
  // Block getting and setting values
  public function __get($name) {}
  public function __set($name, $value) {}
  
  
  protected function _fetch()
  {
    if(!empty($this->_plugins))
      return;
    
    $this->_data =
      DatabaseQuery::create($this->_registry->database)
        ->select('name', 'enabled')
        ->from($this->_table)
        ->fetchAssoc();
    
    // Loop through all plugin files
    $plugins = array();
    foreach(glob($this->_registry->site_dir . 'plugins/*.php') as $file)
    {
      Includes::requireOnce($file);
      
      $name   = pathinfo($file, PATHINFO_FILENAME);
      $plugin = new $name();
      
      // If plugin is already registered, set enabled state
      if(isset($this->_data[$name]))
      {
        $plugin->enabled = $this->_data[$name];
      }
      // Otherwise, enable plugin and save to database
      else
      {
        $plugin->enabled = true;
        $plugin->save();
      }
      
      $plugins[$name] = $plugin;
    }
    
    $this->_data = $plugins;
    
    // Delete old plugins
    /*DatabaseQuery::create($this->_registry->database)
      ->delete()
      ->from($this->_table)
      ->where('name NOT IN (?)', implode('","', array_keys($this->_data)))
      ->query();*/
  }
}