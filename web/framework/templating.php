<?php

require_once(FRAMEWORK.'Mustache/Autoloader.php');
Mustache_Autoloader::register();

Flight::register('view', 'Mustache_Engine', [[
    'cache' => CACHE,
    'loader' => new Mustache_Loader_FilesystemLoader(THEMES.Flight::config()->get('config.theme'))
]]);

Flight::map('render', function ($template, $data = null) {
    $tpl = Flight::view()->loadTemplate($template);
    print $tpl->render($data);
});

Flight::map('buildPage', function ($template, $data = null) {
    Flight::render('framework/header', [
        'theme' => Flight::config()->get('config.theme'),
        'title' => $data['title'].' | '.Flight::config()->get('template.title'),
        'logo' => Flight::config()->get('template.logo')
        //'xajax' => $xajax->printJavascript("scripts", "xajax.js")
    ]);
    Flight::render('framework/navbar', $data['navbar']);
    Flight::render($template, $data);
    Flight::render('framework/footer', [
        'version' => Flight::get('version'),
        'git' => Flight::get('git'),
        'dev' => Flight::get('dev'),
        'quote' => Flight::getQuote()
    ]);
});
