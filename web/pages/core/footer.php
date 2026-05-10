<?php
global $theme, $userbank;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

// Suffix the footer with the short SHA when we have one. Both empty
// branches (an empty string from a missing-git fallback, or the literal
// `0` from the unresolved sentinel / the test bootstrap) are PHP-falsy,
// so this gates on the presence of an actual SHA without needing a
// separate "is this a dev build?" boolean.
$theme->assign('git', SB_GITREV ? ' | Git: '.SB_GITREV : '');
$theme->assign('version', SB_VERSION);
$theme->assign('query', !empty($GLOBALS['server_qry']) ? $GLOBALS['server_qry'] : '');

// Issue #1304: server-render the command palette's "Navigate" entries
// as a permission-filtered JSON blob the chrome emits inside a
// `<script type="application/json" id="palette-actions">` tag in
// `core/footer.tpl`. theme.js reads + JSON.parses the blob at boot;
// pre-fix the entry list was a hardcoded JS array that leaked admin
// links (`Admin panel`, `Add ban`) to logged-out / partial-permission
// users.
//
// JSON_HEX_TAG / _AMP / _APOS / _QUOT escape `<>&'"` as Unicode
// escapes so the JSON content can never break out of the surrounding
// `<script>` element regardless of what a future label / href adds.
// JSON_THROW_ON_ERROR makes encoding failures loud (the catalog is
// pure ASCII today, but the throw protects future edits).
$paletteActions = \Sbpp\View\PaletteActions::for($userbank ?? null);
$theme->assign(
    'palette_actions_json',
    json_encode(
        $paletteActions,
        JSON_THROW_ON_ERROR
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT,
    ),
);

$theme->display('core/footer.tpl');
