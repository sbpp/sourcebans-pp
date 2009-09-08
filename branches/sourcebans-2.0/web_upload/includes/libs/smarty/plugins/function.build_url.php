<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {build_url _=file name=value ...} function plugin
 *
 * Type:    function<br>
 * Name:    build url<br>
 * Purpose: build URL
 * @link    http://www.sourcebans.net
 * @author  InterWave Studios
 * @param   array
 * @param   Smarty
 * @return  string
 */
function smarty_function_build_url($params, &$smarty)
{
  if(isset($params['_']))
  {
    $file = $params['_'];
    unset($params['_']);
  }
  else
    $file = Env::get('active');
  
  $url       = dirname($_SERVER['SCRIPT_NAME']) . '/' . $file;
  if(!empty($params))
  {
    ksort($params);
    $url .= '?' . http_build_query($params, '', '&amp;');
  }
  list($url) = SBPlugins::call('OnBuildUrl', $url);
  
  return $url;
}
?>