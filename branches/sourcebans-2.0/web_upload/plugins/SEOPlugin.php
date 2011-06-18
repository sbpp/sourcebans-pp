<?php
/**
 * Search Engine Optimization plugin
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Plugin
 * @version    $Id$
 */
class SEOPlugin extends SBPlugin
{
  protected $name        = 'Search Engine Optimization';
  protected $author      = 'Tsunami';
  protected $description = 'Converts URLs into search engine optimized URLs.';
  protected $version     = '1.0';
  protected $url         = 'http://www.sourcebans.net';
  
  
  public function OnBuildUri($controller, $action, $data)
  {
    $uri = '';
    if($controller != 'index')
      $uri .= $controller;
    if($action     != 'index')
      $uri .= '/' . $action;
    
    foreach($data as $name => $value)
      $uri .= '/' . $name . '=' . $value;
    
    return ($uri == '' ? '?' : $uri);
  }
  
  // No hook for OnParseUri as it's the same as the default
}