<?php
// Auto-prepended on every request inside the dev container.
//
// init.php has a guard:
//   if ($_SERVER['HTTP_HOST'] != "localhost" && !defined("IS_UPDATE")) {
//       if (file_exists(ROOT."/install")) { die('Please delete the install directory'); }
//   }
// That bare-string match doesn't accept "localhost:8080" or "127.0.0.1", so
// the panel refuses to load. Strip the port for any loopback host so the
// guard sees the value it expects without weakening it for real deployments.
if (isset($_SERVER['HTTP_HOST'])
    && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}
