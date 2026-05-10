<?php
declare(strict_types=1);

// Install wizard entry point.
//
// Lifecycle (#1332):
//   1. install/init.php — paths only, no vendor/ dependencies.
//   2. Vendor check — short-circuit to install/recovery.php if
//      web/includes/vendor/autoload.php is missing.
//   3. install/bootstrap.php — Composer autoload + Smarty.
//   4. Routing — ?step=<1..6> dispatches to install/pages/page.<N>.php.
//      Each page handler builds a Sbpp\View\Install\Install*View DTO
//      and calls Sbpp\View\Renderer::render() against $installTheme.
//
// Steps:
//   1 — License agreement
//   2 — Database details (DB host / user / pass / name / prefix +
//       Steam API key + admin email)
//   3 — Environment + DB requirements check
//   4 — Schema install (struc.sql)
//   5 — Initial admin setup (admin user/pass/Steam ID/email),
//       config.php write, data.sql seed
//   6 — Optional AMXBans import
//
// Pre-#1332 the wizard pulled MooTools + a wizard-local sourcebans.js
// for ShowBox()/$()/$E() helpers; both files were broken since #1123 D1
// deleted the sister files in web/scripts/. The new wizard is vanilla
// JS-free for navigation (forms POST natively) and uses inline
// vanilla JS only for the license accept-checkbox guard on step 1.

require_once __DIR__ . '/init.php';

// C3 short-circuit. Anything past this point may reference Sbpp\…
// classes; before this point, never.
if (!file_exists(PANEL_INCLUDES_PATH . '/vendor/autoload.php')) {
    require_once __DIR__ . '/recovery.php';
    exit;
}

require_once __DIR__ . '/bootstrap.php';

$step = filter_input(INPUT_GET, 'step', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 6, 'default' => 1],
]);
// FILTER_VALIDATE_INT with a `default` option returns the int OR the
// default — never `false`. The `?? 1` handles the `null` case where
// the request doesn't carry a `step` query param at all.
$step = $step ?? 1;

require_once INCLUDES_PATH . '/routing.php';
/** @var \Smarty\Smarty $installTheme */
$installTheme = $GLOBALS['installTheme'];
sbpp_install_dispatch($step, $installTheme);
