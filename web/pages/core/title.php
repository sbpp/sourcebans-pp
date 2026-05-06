<?php
global $theme;

// #1207 AUTH-3: pages whose audience is logged-out by definition
// (login + lostpassword) publish their own breadcrumb shape on the
// matching View DTO so we don't render a misleading "Home > Login"
// — clicking "Home" just lands the visitor on the public dashboard
// they didn't intend to visit. The default 2-segment breadcrumb
// stays for every other route. The dispatch happens here (not in
// the page handler) because the lifecycle is
// header → navbar → title → page → footer; the page handler runs
// AFTER `$breadcrumb` has already been emitted to the wire.
//
// `$page_slug` (NOT `$page`) on purpose: this file is `require_once`'d
// from the global `build($title, $page)` helper in
// `web/includes/page-builder.php`, where `$page` is the path to the
// page handler this title block will be followed by. A `$page = …`
// assignment here would overwrite that parameter via the shared
// scope and the next `require_once(TEMPLATES_PATH.$page)` would
// resolve to garbage (e.g. `pages` + `login` = `pageslogin`).
$page_slug = (string) ($_GET['p'] ?? '');
$breadcrumb = match ($page_slug) {
    'login' => \Sbpp\View\LoginView::breadcrumb(),
    'lostpassword' => \Sbpp\View\LostPasswordView::breadcrumb(),
    default => [
        [
            'title' => 'Home',
            'url' => 'index.php?p=home',
        ],
        [
            'title' => $title,
            'url' => 'index.php?p=' . urlencode($page_slug),
        ],
    ],
};

$theme->assign('board_name', Config::get('template.title'));
$theme->assign('title', $title);
$theme->assign('breadcrumb', $breadcrumb);
$theme->display('core/title.tpl');
