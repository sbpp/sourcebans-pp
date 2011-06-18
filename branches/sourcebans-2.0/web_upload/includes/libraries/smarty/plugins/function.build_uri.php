<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {build_uri controller=controller action=action name=value ...} function plugin
 *
 * Type:    function<br>
 * Name:    build uri<br>
 * Purpose: builds a URI
 * @link    http://www.sourcebans.net
 * @author  InterWave Studios
 * @param   array
 * @param   Smarty
 * @return  string
 */
function smarty_function_build_uri($params, &$smarty)
{
  $controller = null;
  $action     = null;
  if(isset($params['controller']))
  {
    $controller = $params['controller'];
    unset($params['controller']);
  }
  if(isset($params['action']))
  {
    $action     = $params['action'];
    unset($params['action']);
  }
  
  return new SBUri($controller, $action, $params);
}
?>