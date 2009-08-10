<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {build_query name=value ...} function plugin
 *
 * Type:     function<br>
 * Name:     build query<br>
 * Purpose:  build query string
 * @link http://www.sourcebans.net
 * @author  SourceBans Development Team
 * @param array
 * @param Smarty
 * @return string
 */
function smarty_function_build_query($params, &$smarty)
{
  $query = $_GET;
  
  foreach($params as $name => $value)
  {
    if($value == false)
      unset($query[$name]);
    else
      $query[$name] = $value;
  }
  
  ksort($query);
  return '?' . http_build_query($query, '', '&amp;');
}
?>