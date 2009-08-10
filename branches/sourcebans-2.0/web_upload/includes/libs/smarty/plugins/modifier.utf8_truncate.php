<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */
require_once UTILS;


/**
 * Smarty UTF-8 truncate modifier plugin
 *
 * Type:     modifier<br>
 * Name:     truncate<br>
 * Purpose:  Truncate a string to a certain length if necessary,
 *           optionally splitting in the middle of a word, and
 *           appending the $etc string or inserting $etc into the middle.
 * @link http://smarty.php.net/manual/en/language.modifier.truncate.php
 *          truncate (Smarty online manual)
 * @author   Monte Ohrt <monte at ohrt dot com>
 * @param string
 * @param integer
 * @param string
 * @param boolean
 * @param boolean
 * @return string
 */
function smarty_modifier_utf8_truncate($string, $length = 80, $etc = '...',
                                       $break_words = false, $middle = false)
{
    return Util::trunc($string, $length, $etc, $break_words, $middle);
}

/* vim: set expandtab: */

?>
