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
  return Util::buildQuery($params);
}
?>