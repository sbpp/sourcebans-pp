<?php
global $userbank, $theme, $xajax;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

Template::render('core/header', [
    'title' => $title.' | '.Config::get('template.title'),
    'logo' => Config::get('template.logo'),
    'theme' => (Config::get('config.theme')) ? Config::get('config.theme') : 'default',
    'xajax' => $xajax->printJavascript("scripts", "xajax.js")
]);
