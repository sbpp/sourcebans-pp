<?php
/**
 * This file contains a list of tabs
 * @package SourceBans
 * @subpackage Template
 */
class Tabs
{
  /**
   * This is an array of tabs
   *
   * @var array List of tabs
   */
  private static $tabs = array();
  
  
  /**
   * Adds a tab to the list
   *
   * @param string $url  the url to link to
   * @param string $name the name of the tab
   * @param string $desc the description of the tab
   */
  public static function add($url, $name, $desc)
  {
    self::$tabs[] = array('url'  => $url,
                          'name' => $name,
                          'desc' => $desc);
  }
  
  
  /**
   * Returns the list of tabs
   */
  public static function getTabs()
  {
    return self::$tabs;
  }
}
?>