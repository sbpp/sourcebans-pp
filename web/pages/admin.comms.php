<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

global $userbank, $theme;
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

/*
 * #1239 — single-section page; the legacy chrome rendered an
 * `AdminTabs([...])` strip with one button ("Add a block") that
 * called the now-removed `openTab()` JS handler. With only one
 * destination there's nothing to route to, so we drop the strip
 * entirely (the surface is reachable from the comms list's "Add a
 * block" CTA + the sidebar). The `.tabcontent` wrapper is gone for
 * the same reason.
 */

if (isset($_GET['mode']) && $_GET['mode'] == "delete") {
    // sb.message (sb.js) replaces the v1.x ShowBox helper.
    echo "<script>sb.message.show('Ban Deleted', 'The ban has been deleted from SourceBans', 'green', '', true);</script>";
} elseif (isset($_GET['mode']) && $_GET['mode']=="unban") {
    // sb.message (sb.js) replaces the v1.x ShowBox helper.
    echo "<script>sb.message.show('Player Unbanned', 'The Player has been unbanned from SourceBans', 'green', '', true);</script>";
}

if (isset($GLOBALS['IN_ADMIN'])) {
    define('CUR_AID', $userbank->GetAid());
}


// Self-contained reblock / paste-block / block-from-ban prefill
// (replaces the v1.x LoadPrepareReblock / LoadPrepareBlockFromBan /
// LoadPasteBlock / ShowBox / applyBlockFields helpers). Built on
// sb.api.call + window.__sbppApplyBlockFields (defined in this file's
// tail script).
if (isset($_GET["rebanid"])) {
    echo '<script type="text/javascript">sb.ready(function(){sb.api.call(Actions.CommsPrepareReblock,{bid:' . (int) $_GET["rebanid"] . '}).then(function(r){if(r&&r.ok&&r.data&&typeof window.__sbppApplyBlockFields==="function")window.__sbppApplyBlockFields(r.data);});});</script>';
} elseif (isset($_GET["blockfromban"])) {
    echo '<script type="text/javascript">sb.ready(function(){sb.api.call(Actions.CommsPrepareBlockFromBan,{bid:' . (int) $_GET["blockfromban"] . '}).then(function(r){if(r&&r.ok&&r.data&&typeof window.__sbppApplyBlockFields==="function")window.__sbppApplyBlockFields(r.data);});});</script>';
} elseif ((isset($_GET['action']) && $_GET['action'] == "pasteBan") && isset($_GET['pName']) && isset($_GET['sid'])) {
    $pNameJs = json_encode((string) $_GET['pName'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<script type=\"text/javascript\">sb.ready(function(){sb.message.show('Loading..','<b>Loading...</b><br><i>Please Wait!</i>','blue','',true);sb.hide('dialog-control');sb.api.call(Actions.CommsPaste,{sid:" . (int) $_GET['sid'] . ",name:" . $pNameJs . ",type:0}).then(function(r){if(r&&r.ok&&r.data){if(typeof window.__sbppApplyBlockFields==='function')window.__sbppApplyBlockFields(r.data);sb.show('dialog-control');sb.hide('dialog-placement');}else if(r&&r.ok===false&&r.error){sb.message.error('Error',r.error.message);sb.show('dialog-control');}});});</script>";
}

/*
 * Smart-default pre-fill for SteamID via `?steam=…&type=…`
 * (mirrors `admin.bans.php`'s sibling block for the "Ban player"
 * menu item — see #PLAYER_CTX_MENU / #1395). The public servers
 * list's right-click context menu's "Block comms" item lands
 * admins on `?p=admin&c=comms&steam=STEAM_…&type=0` to pre-populate
 * the form without firing a JSON action — the form has to be
 * usable on the no-JS path (every other approach taken by the
 * legacy `pages/admin.blockit.php?check=…&type=0` URL routed to a
 * chromeless iframe page whose relative POST hit `/pages/api.php`
 * → 404). The pre-fill happens server-side via the View DTO
 * rather than through `__sbppApplyBlockFields` so the surface
 * works pre-JS-boot.
 *
 * Allowed shapes (mirrors admin.bans.php's allowlist verbatim so
 * the menu's URL contract is symmetric across the two
 * affordances): STEAM_X:Y:Z / [U:1:N] / 17-digit SteamID64 /
 * dotted IPv4. Comms doesn't actually ban by IP, but keeping the
 * regex symmetric with bans means a future menu / deep-link
 * change only has to touch one allowlist. An IPv4 value lands in
 * the steam input and will fail server-side validation on submit
 * via `Actions.CommsAdd`; that's the right behaviour (loud
 * failure) vs. silently dropping a value the user can see.
 *
 * Allowed `type` values are 1 (Mute), 2 (Gag), 3 (Silence) — the
 * `:prefix_comms.type tinyint` column's domain. Anything else
 * (including the menu's `?type=0` bridging value, which is
 * sourced from the bans-menu URL shape where 0=Steam ID) falls
 * back to 0 (no pre-selection) and the form's `<select id="type">`
 * lands on the native first-option default (Mute).
 */
$prefillSteamRaw = isset($_GET['steam']) ? trim((string) $_GET['steam']) : '';
$prefillTypeRaw  = isset($_GET['type']) ? (int) $_GET['type'] : 0;
$prefillNameRaw  = isset($_GET['name']) ? (string) $_GET['name'] : '';
$prefillSteam    = '';
$prefillType     = 0;
if ($prefillSteamRaw !== '') {
    if (preg_match('/^(?:STEAM_[01]:[01]:\d+|\[U:1:\d+\]|\d{17}|\d{1,3}(?:\.\d{1,3}){3})$/', $prefillSteamRaw) === 1) {
        $prefillSteam = $prefillSteamRaw;
        $prefillType  = in_array($prefillTypeRaw, [1, 2, 3], true) ? $prefillTypeRaw : 0;
    }
}
/*
 * Issue #1440 — `?name=<player>` smart-default companion to
 * `?steam=…`, single sanitation contract across both menu-target
 * surfaces via `Sbpp\Util\PlayerName::sanitisePrefill`. See the
 * helper's class docblock for the full strip set and the
 * sibling block in `admin.bans.php` for the decoupling
 * rationale ("name is its own thing"; `?name=` survives even
 * when `?steam=` is missing or invalid — the operator landed
 * here because they want to type a block, the nickname is a
 * convenience not a gating dependency).
 */
$prefillName = \Sbpp\Util\PlayerName::sanitisePrefill($prefillNameRaw);

// SourceComms reuses the bans permission set: there is no
// ADMIN_ADD_COMM flag, so the gate uses ADMIN_OWNER|ADMIN_ADD_BAN.
// Splatting Perms::for(...) into the View pulls `can_add_ban` (and
// the owner-bypass) without re-deriving the bitmask here; the
// view-level property name stays `permission_addban` to match the
// legacy default-theme template's existing reference (#1123 A3 +
// SmartyTemplateRule's per-leg cross-check).
$perms = \Sbpp\View\Perms::for($userbank);
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminCommsAddView(
    permission_addban: $perms['can_add_ban'],
    prefill_steam: $prefillSteam,
    prefill_type: $prefillType,
    prefill_name: $prefillName,
));
?>
<script type="text/javascript">
// `changeReason` toggles the freeform `#dreason` textarea container
// when the operator picks "other" from the reason `<select>`. The
// `<select onchange="...">` attribute in page_admin_comms_add.tpl
// keeps invoking this helper; the rewrite to vanilla DOM landed
// alongside #1420 (the legacy `$('id').setStyle(...)` shape relied
// on the MooTools-compat wrapping in sb.js — vanilla DOM is the
// modern convention per AGENTS.md's "vanilla DOM helpers" rule and
// removes one layer of indirection between the click and the
// visible state change).
function changeReason(szListValue)
{
    var el = document.getElementById('dreason');
    if (el) el.style.display = (szListValue == "other" ? "block" : "none");
}

// #1420: pre-fix this file shipped a global `ProcessBan()` that
// the submit button invoked via `onclick="ProcessBan();"`. That
// helper walked the form via the legacy MooTools `$('id')` shim
// (still working via sb.js's `global.$`) and emitted feedback
// through `sb.message.show` / `sb.message.error`, which paint into
// `#dialog-placement` / `#dialog-title` (v1.x chrome ids the v2.0
// theme doesn't render). The submit path was effectively silent
// on every error branch — the bug the reporter filed.
// The replacement lives in page_admin_comms_add.tpl's inline IIFE
// (mirrors page_admin_bans_add.tpl's shape): native HTML
// validation first, then sb.api.call(Actions.CommsAdd), then
// window.SBPP.showToast on the error envelope. The global is
// gone; nothing in the codebase references it under the v2.0
// theme.

// Self-contained DOM-prefill helper (replaces the v1.x applyBlockFields)
// so reblock / blockfromban / pasteBlock all keep prefilling the form.
window.__sbppApplyBlockFields = function (d) {
    var byId = function (id) { return document.getElementById(id); };
    if (byId('nickname'))   byId('nickname').value   = d.nickname || '';
    if (byId('fromsub'))    byId('fromsub').value    = d.subid    || '';
    if (byId('steam'))      byId('steam').value      = d.steam    || '';
    if (byId('txtReason'))  byId('txtReason').value  = '';
    if (typeof window.selectLengthTypeReason === 'function') {
        window.selectLengthTypeReason(d.length || 0, d.type || 0, d.reason || '');
    }
    if (typeof window.swapTab === 'function') window.swapTab(0);
};
</script>
