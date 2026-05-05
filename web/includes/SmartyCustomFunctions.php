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

/**
 * Smarty {csrf_field} function plugin: renders the hidden CSRF token input.
 */
function smarty_function_csrf_field()
{
    $token = htmlspecialchars(CSRF::token(), ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars(CSRF::FIELD_NAME, ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="' . $name . '" value="' . $token . '" />';
}

/**
 * Smarty {has_access flag=…}…{/has_access} BLOCK plugin: render the inner
 * content only when the current user holds the given permission flag.
 *
 * This is the deliberate ESCAPE HATCH for ad-hoc per-row checks (e.g.
 * gating an action button inside a `{foreach}` over admins). The
 * PRIMARY pattern stays precomputed `can_*` booleans on the View — see
 * `Sbpp\View\Perms::for()` and the convention block on
 * `Sbpp\View\View` — so most templates should keep using
 * `{if $can_add_ban} … {/if}`.
 *
 * Usage examples (the `flag=` parameter accepts either a web-flag
 * integer or a SourceMod char-flag string; `CUserManager::HasAccess()`
 * dispatches on the type):
 *
 * ```smarty
 * {has_access flag=$smarty.const.ADMIN_ADD_BAN}
 *     <a href="?p=admin&c=bans&o=add">Add ban</a>
 * {/has_access}
 *
 * {foreach $admins as $a}
 *     {has_access flag=$smarty.const.ADMIN_EDIT_ADMINS}
 *         <a href="?p=admin&c=admins&o=edit&id={$a.aid}">Edit</a>
 *     {/has_access}
 * {/foreach}
 * ```
 *
 * Smarty 5 invokes block plugins twice per render — once on the
 * opening tag (`$content === null`, `$repeat === true`) and once on
 * the closing tag (`$content` populated, `$repeat === false`). We
 * suppress output on the opening pass and gate on the closing pass.
 *
 * Reads `$userbank` from the request-global the rest of the panel
 * exposes (set by `init.php`; same convention as every page handler's
 * `global $userbank;`). When unset (no session, no user manager
 * bound — should not happen in normal request flow), the plugin
 * fails closed and emits nothing.
 *
 * Owner bypass (numeric web flags only): `ADMIN_OWNER` is OR'd into
 * the mask before the `HasAccess()` call, mirroring `Sbpp\View\Perms::for()`
 * and the `CheckAdminAccess(ADMIN_OWNER|…)` convention used throughout
 * `web/includes/page-builder.php`. This guarantees the two helpers
 * agree for owners — `{if $can_add_ban}` and
 * `{has_access flag=$smarty.const.ADMIN_ADD_BAN}` evaluate identically
 * regardless of which one the template author reaches for. Char-flag
 * strings (SourceMod's `'a'`–`'z'`) are a separate permission system
 * with their own root flag (`SM_ROOT='z'`) that `HasAccess()` already
 * matches via substring scan; the OR is skipped on that path.
 *
 * @param array{flag?: int|string} $params
 * @param string|null              $content
 * @param mixed                    $template
 * @param bool                     $repeat
 */
function smarty_block_has_access(array $params, ?string $content, $template, &$repeat): string
{
    if ($content === null) {
        return '';
    }
    global $userbank;
    if (!$userbank instanceof CUserManager) {
        return '';
    }
    $flag = $params['flag'] ?? null;
    if ($flag === null || $flag === '' || $flag === 0) {
        return '';
    }
    if (is_numeric($flag) && defined('ADMIN_OWNER')) {
        $flag = (int) $flag | (int) constant('ADMIN_OWNER');
    }
    return $userbank->HasAccess($flag) ? $content : '';
}