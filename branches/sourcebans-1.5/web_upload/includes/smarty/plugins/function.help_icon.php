<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {help_icon title="gaben" desc="hello"} function plugin
 *
 * Type:    function<br>
 * Name:    help tip<br>
 * Purpose: show help tip
 * @link    http://www.sourcebans.net
 * @author  GameConnect
 * @param   array
 * @param   Smarty
 * @return  string
 */
function smarty_function_help_icon($params, &$smarty)
{
	 return '<img alt="Help" class="tips" src="images/help.png" title="' . $params['title'] . ' :: ' . $params['desc'] . '" />&nbsp;';
}