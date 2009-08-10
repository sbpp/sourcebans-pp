<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {sb_button text="Login" onclick=$redir class="ok" id="alogin" submit=false} function plugin
 *
 * Type:     function<br>
 * Name:     help tip<br>
 * Purpose:  show help tip
 * @link http://www.sourcebans.net
 * @author  SourceBans Development Team
 * @param array
 * @param Smarty
 * @return string
 */
function smarty_function_sb_button($params, &$smarty) //$text, $click, $class, $id="", $submit=false
{
	$text = $params['text'];
	$click = $params['onclick'];
	$class = $params['class'];
	$id = $params['id'];
	$submit = $params['submit'];	
	
	$type = $submit ? "submit" : "button";
	$button = "<input type='$type' onclick=\"$click\" name='$id' class='btn $class' onmouseover='ButtonOver(\"$id\")' onmouseout='ButtonOver(\"$id\")' id='$id' value='$text' />";
	return $button;
}



?>
