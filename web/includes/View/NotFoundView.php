<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * 404 "page not found" view — binds to `page_404.tpl`.
 *
 * Used by `route()` (web/includes/page-builder.php) when the URL
 * resolves to a sub-route that doesn't exist. Currently the only
 * caller is the `?p=admin&c=<unknown>` default branch (#1207 ADM-1):
 * unrecognised `c=…` values used to fall through to the admin home,
 * which made typos and stale bookmarks invisible. The route now
 * returns an HTTP 404 status alongside this template so the chrome
 * still renders (sidebar + topbar + footer) but the page slot is
 * the error message.
 *
 * The chrome itself is intentionally NOT suppressed here — keeping
 * the sidebar visible lets the user navigate away without a back
 * button, and the page-builder pipeline (header → navbar → title →
 * page → footer) is the same shape every other page uses, so
 * SmartyTemplateRule and the rest of the View DTO contract stay
 * uniform. The 404 status code goes out via `http_response_code(404)`
 * in `route()`, so search crawlers and monitoring tools see the
 * correct signal.
 *
 * The View declares no properties: the message is static, so the
 * template renders without per-request data. The class exists so
 * the page handler can use `Renderer::render()` instead of an
 * untyped `$theme->display(...)` call, and so SmartyTemplateRule
 * guards the template against accidentally growing untyped variable
 * references in a follow-up.
 */
final class NotFoundView extends View
{
    public const TEMPLATE = 'page_404.tpl';
}
