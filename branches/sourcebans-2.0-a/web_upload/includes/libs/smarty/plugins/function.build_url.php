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
 * Purpose: builds a URL
 * @link    http://www.sourcebans.net
 * @author  InterWave Studios
 * @param   array
 * @param   Smarty
 * @return  string
 */
function smarty_function_build_url($params, &$smarty)
{
  return Util::buildUrl($params);
}
?>