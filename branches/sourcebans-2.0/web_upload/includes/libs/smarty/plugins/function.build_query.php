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
 * Purpose: build query string
 * @link    http://www.sourcebans.net
 * @author  InterWave Studios
 * @param   array
 * @param   Smarty
 * @return  string
 */
function smarty_function_build_query($params, &$smarty)
{
  $file  = Env::get('active');
  $query = $_GET;
  
  foreach($params as $name => $value)
  {
    if(is_null($value))
      unset($query[$name]);
    else
      $query[$name] = $value;
  }
  
  ksort($query);
  $url       = dirname($_SERVER['SCRIPT_NAME']) . '/' . $file . '?' . http_build_query($query, '', '&amp;');
  list($url) = SBPlugins::call('OnBuildQuery', $url);
  
  return $url;
}
?>