<?php
global $userbank, $theme, $xajax;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

$theme->assign('title', $title.' | '.Config::get('template.title'));
$theme->assign('logo', Config::get('template.logo'));
$theme->assign('theme', (Config::get('config.theme')) ? Config::get('config.theme') : 'default');
$theme->assign('xajax', $xajax->printJavascript("scripts", "xajax.js"));
$theme->display('core/header.tpl');
