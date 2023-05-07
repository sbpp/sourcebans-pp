<?php
/**
 * Smarty {help_icon title="gaben" message="hello"} function plugin
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
function smarty_function_help_icon($params, ...$args)
{
	 return '<img border="0" align="absbottom" src="images/help.png" class="tip" title="' .  $params['title'] . ' :: ' .  $params['message'] . '">&nbsp;&nbsp;';
}

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
function smarty_function_sb_button($params) //$text, $click, $class, $id="", $submit=false
{
	$text = $params['text'] ?? "";
	$click = $params['onclick'] ?? "";
	$class = $params['class'] ?? "";
	$id = $params['id'] ?? "";
	$submit = $params['submit'] ?? "";
	
	$type = $submit ? "submit" : "button";
	$button = "<input type='$type' onclick=\"$click\" name='$id' class='btn $class' onmouseover='ButtonOver(\"$id\")' onmouseout='ButtonOver(\"$id\")' id='$id' value='$text' />";
	return $button;
}

/**
 * Smarty {load_template file="pages.file"} without the `.php` extension. Function plugin.
 *
 * Type:     function<br>
 * Name:     Load template
 * Purpose:  Load template files
 * @link http://www.sourcebans.net
 * @author  SourceBans Development Team
 * @param array $params
 */
function smarty_function_load_template(array $params): void
{
    require TEMPLATES_PATH . "/{$params['file']}.php";
}

/**
 *  Smarty {smarty_stripslashes} function plugin
 * 
 * Type:     function<br>
 * Name:     smarty_stripslashes<br>
 * Purpose:  custom stripslashes function
 * @link https://github.com/lechuga16/sourcebans-pp/tree/smarty_stripslashes
 * @author  Lechuga
 * @param array $params
 * @return string
 * @version 1.0
 */
function smarty_stripslashes($string)
{
	return stripslashes($string);
}

/**
 *  Smarty {smarty_htmlspecialchars} function plugin
 * 
 * Type:     function<br>
 * Name:     smarty_htmlspecialchars<br>
 * Purpose:  custom htmlspecialchars function
 * @link https://github.com/lechuga16/sourcebans-pp/tree/smarty_stripslashes
 * @author  Lechuga
 * @param array $params
 */
function smarty_htmlspecialchars($string, $flags = ENT_COMPAT | ENT_HTML401, $encoding = 'UTF-8', $double_encode = true) {
    return htmlspecialchars($string, $flags, $encoding, $double_encode);
}