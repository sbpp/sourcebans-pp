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

    /**
     * Breadcrumb shape for the lost-password page (#1207 AUTH-3).
     *
     * Single-segment "Reset password" rather than the default
     * "Home > $title" pair. Same reasoning as
     * {@see LoginView::breadcrumb()}: a visitor recovering their
     * password is by definition logged out and has no meaningful
     * "Home" — the default breadcrumb's prefix link just bounces
     * them back to the public dashboard, which isn't useful.
     *
     * `core/title.php` consults this via `$_GET['p'] === 'lostpassword'`
     * BEFORE the page handler runs, so the shape lives on the View
     * as a static method.
     *
     * @return list<array{title: string, url: string}>
     */
    public static function breadcrumb(): array
    {
        return [
            ['title' => 'Reset password', 'url' => 'index.php?p=lostpassword'],
        ];
    }
}
