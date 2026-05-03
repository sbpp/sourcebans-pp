<?php
global $theme;

$breadcrumb = [
    [
        'title' => 'Home',
        'url' => 'index.php?p=home'
    ],
    [
        'title' => $title,
        'url' => 'index.php?p=' . urlencode((string) ($_GET['p'] ?? ''))
    ]
];

$theme->assign('board_name', Config::get('template.title'));
$theme->assign('title', $title);
$theme->assign('breadcrumb', $breadcrumb);
$theme->display('core/title.tpl');
