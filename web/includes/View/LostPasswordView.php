<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Lost-password page — binds to `page_lostpassword.tpl`.
 *
 * The form itself takes no PHP-side data: the recovery flow runs entirely
 * over the JSON API (`Actions.AuthLostPassword`), so the template is a
 * static card with a vanilla-JS submit handler. The View therefore declares
 * no properties — it exists only so the page handler can switch from the
 * untyped `$theme->display(...)` call to the typed
 * {@see Renderer::render()} pipeline, and so SmartyTemplateRule can guard
 * the template against accidentally growing untyped variable references.
 *
 * The reset-link branch in `web/pages/page.lostpassword.php` (the
 * `?email=…&validation=…` URL the password-reset email links to) still
 * uses the legacy inline `<script>ShowBox(...)</script>` + `PageDie()`
 * shape and never reaches this View. That branch is out of scope for the
 * Phase B form restyle; a follow-up moves it onto the new toast helper.
 */
final class LostPasswordView extends View
{
    public const TEMPLATE = 'page_lostpassword.tpl';
}
