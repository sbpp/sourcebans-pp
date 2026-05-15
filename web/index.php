<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

include_once 'init.php';
include_once(INCLUDES_PATH . "/system-functions.php");
include_once('config.php');
include_once(INCLUDES_PATH . "/page-builder.php");

$route = route(Config::get('config.defaultpage'));
build($route[0], $route[1]);
