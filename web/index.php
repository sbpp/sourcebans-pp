<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

include_once 'init.php';
include_once(INCLUDES_PATH . "/system-functions.php");
// HIGH-1 of the #1381 review: do NOT include `config.php` here.
// `init.php` already loaded it via `sbpp_resolve_config_path()`
// (which honours `SBPP_CONFIG_PATH` for Docker-secret mounts);
// a second literal `include_once('config.php')` would either
// fail (no `config.php` at `web/`) or shadow the secret-mounted
// values with a stale on-disk copy.
include_once(INCLUDES_PATH . "/page-builder.php");

$route = route(Config::get('config.defaultpage'));
build($route[0], $route[1]);
