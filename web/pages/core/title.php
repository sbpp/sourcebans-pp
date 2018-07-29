<?php
$breadcrumb = [
    [
        'title' => 'Home',
        'url' => 'index.php?p=home'
    ],
    [
        'title' => $title,
        'url' => 'index.php?p='.filter_input(INPUT_GET, 'p', FILTER_SANITIZE_STRING)
    ]
];

Template::render('core/title', [
    'title' => $title,
    'breadcrumb' => $breadcrumb
]);
