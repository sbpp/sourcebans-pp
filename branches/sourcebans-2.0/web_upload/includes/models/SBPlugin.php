<?php
/**
 * SourceBans plugin model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Plugins
 * @version    $Id$
 */
abstract class SBPlugin extends BaseRowModel
{
  protected $_key = 'name';
  
  // These protected variables are not prefixed with an underscore
  // to avoid confusion when setting them from a sub-class
  protected $author;
  protected $description;
  protected $name;
  protected $version;
  protected $url;
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'plugins';
  }
  
  public function __get($name)
  {
    // TODO: Use isset that doesn't call parent::__isset?
    switch($name)
    {
      case 'author':
      case 'description':
      case 'name':
      case 'version':
      case 'url':
        return $this->$name;
      default:
        return parent::__get($name);
    }
  }
}