<?php
global $userbank, $theme;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

$theme->assign('title', $title.' | '.Config::get('template.title'));
// `template.logo` is the operator-configurable brand mark; resolve
// through `Sbpp\View\BrandLogo` so a configured value that's null /
// empty / points at the v1.x default (`logos/sb-large.png`, which
// the v2.0 default theme never shipped) / points at a deleted file
// falls back to `images/favicon.svg` rather than emitting a broken
// `<img>` against the sidebar mark. Pre-fix the chrome read the raw
// `Config::get('template.logo')` and concatenated it into
// `<img src="{$theme_url}/{$logo}">`, which silently surfaced one of
// three reachable broken-image shapes (see BrandLogo's class
// docblock for the bug surface) instead of the canonical
// SourceBans++ shield.
$theme->assign('logo', \Sbpp\View\BrandLogo::resolve());
$theme->assign('theme', (Config::get('config.theme')) ? Config::get('config.theme') : 'default');
$theme->display('core/header.tpl');
