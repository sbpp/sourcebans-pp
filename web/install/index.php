<?php
require_once('init.php');
require_once(INCLUDES_PATH.'/system-functions.php');
require_once(INCLUDES_PATH.'/routing.php');

$options = [
    'min_range' => 1,
    'max_range' => 6,
    'default' => 1
];
$step = filter_input(INPUT_GET, 'step', FILTER_VALIDATE_INT, ['options' => $options]);

[$title, $page] = route($step);
build($title, $page, $step);
