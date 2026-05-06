<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2026 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

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
