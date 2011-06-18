<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {build_query name=value ...} function plugin
 *
 * Type:    function<br>
 * Name:    build query<br>
 * Purpose: builds a query string
 * @link    http://www.sourcebans.net
 * @author  InterWave Studios
 * @param   array
 * @param   Smarty
 * @return  string
 */
function smarty_function_build_query($params, &$smarty)
{
  $registry = Registry::getInstance();
  $uri      = clone $registry->uri;
  $data     = array();
  
  /*foreach($uri as $name => $value)
  {
    if(!array_key_exists($name, $params))
    {
      $data[$name] = $value;
    }
    else if(!is_null($params[$name]))
    {
      $data[$name] = $params[$name];
    }
  }*/
  
  foreach($params as $name => $value)
  {
    if(is_null($value))
    {
      unset($uri[$name]);
    }
    else
    {
      $uri[$name] = $value;
    }
  }
  foreach($uri as $name => $value)
  {
    $data[$name] = $value;
  }
  
  ksort($data);
  return new SBUri($uri->controller, $uri->action, $data);
}
?>