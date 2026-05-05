<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Edit-existing-ban form on the admin bans page — binds to
 * `page_admin_edit_ban.tpl`.
 *
 *   - `$can_edit_ban`    Defense-in-depth template gate. The page handler
 *                        already early-`PageDie`'s on access denial, so this
 *                        is `true` whenever the form actually renders. The
 *                        template uses `{if NOT $can_edit_ban}` to render
 *                        an "Access denied" card if it is ever reached
 *                        without the gate being honoured upstream.
 *   - `$ban_name`        Current player name on the ban row.
 *   - `$ban_authid`      Current Steam2 authid on the ban row.
 *   - `$ban_ip`          Current IP on the ban row.
 *   - `$ban_demo`        Pre-built "Uploaded: <b>safename</b>" snippet
 *                        (or empty), htmlspecialchars'd in the page handler
 *                        per #1113. Rendered with `{nofilter}`; the safety
 *                        annotation lives at the call site in the template.
 *   - `$customreason`    `false` when custom reasons are disabled, otherwise
 *                        the list of reason strings from `bans.customreasons`.
 *
 * Length / type / current-reason / post-validation errors / success-redirect
 * are NOT Smarty vars: the page handler emits a vanilla `<script>` tail
 * after `Renderer::render` to hydrate the `<select>`s, surface per-field
 * validation errors, and trigger the "saved" toast → redirect.
 */
final class AdminBansEditView extends View
{
    public const TEMPLATE = 'page_admin_edit_ban.tpl';

    /**
     * @param false|list<string> $customreason `false` when custom reasons
     *     are disabled, otherwise the list of reason strings.
     */
    public function __construct(
        public readonly bool $can_edit_ban,
        public readonly string $ban_name,
        public readonly string $ban_authid,
        public readonly string $ban_ip,
        public readonly string $ban_demo,
        public readonly false|array $customreason,
    ) {
    }
}
