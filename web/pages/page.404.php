<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $theme;

/*
 * 404 page (#1207 ADM-1).
 *
 * Currently reached only via `route()`'s `?p=admin&c=<unknown>`
 * branch (web/includes/page-builder.php), but the surface is
 * deliberately not admin-coupled — any future caller can return
 * `['Page not found', '/page.404.php']` after `http_response_code(404)`
 * and the chrome will render around this page slot.
 *
 * The HTTP status is set in `route()` (not here) so it lands before
 * the chrome's header.php emits any output that would otherwise
 * lock the response code in.
 */
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\NotFoundView());
