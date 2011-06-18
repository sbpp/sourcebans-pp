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
 * @author  InterWave Studios
 * @param   array
 * @param   Smarty
 * @return  string
 */
function smarty_function_help_icon($params, &$smarty)
{
  $registry = Registry::getInstance();
  
  return '<img alt="Help" class="tips" src="' . $registry->uri->base . '/images/help.png" title="' . htmlspecialchars($params['title']) . ' :: ' . htmlspecialchars($params['desc']) . '" />&nbsp;';
}
?>