<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Your-account (self-service) page — binds to `page_youraccount.tpl`.
 *
 * Historical note: prior to v2.0.0 the SourceBans++ default theme's
 * `page_youraccount.tpl` used the non-default `-{ … }-` Smarty
 * delimiter pair (a SourceBans-1.x convention). The B20 redesign
 * rewrote the template in **standard** `{ }` Smarty delimiters, so
 * neither a `View::DELIMITERS` override nor a per-page delimiter swap
 * is required.
 *
 * #1207 ADM-9 — permissions block. The pre-fix shape was a flat
 * `web_permissions: false|list<string>` (built by `BitToString` in
 * `system-functions.php`) which the template rendered as a 30-item
 * `<ul>`, hard to scan. The new contract publishes a structured
 * `web_permissions_grouped: list<{key, label, perms}>` built by
 * {@see PermissionCatalog::groupedDisplayFromMask()}, so the template
 * paints a `<section>` per category in a 2–3 column grid. The
 * SourceMod side (`server_permissions`) stays a flat list — the char
 * flags max out at ~14 single-letter entries with no natural
 * categorisation, so a single column under the "SourceMod" heading
 * still reads cleanly.
 */
final class YourAccountView extends View
{
    public const TEMPLATE = 'page_youraccount.tpl';

    /**
     * @param list<array{key: string, label: string, perms: list<string>}> $web_permissions_grouped
     *     Granted web permissions grouped by display category. Empty
     *     list when the user has no web permissions; the template
     *     renders a `data-testid="account-permissions-web-empty"`
     *     branch in that case. Built by
     *     {@see PermissionCatalog::groupedDisplayFromMask()}.
     * @param false|list<string> $server_permissions
     *     `false` when the admin has no SourceMod flags, otherwise
     *     the flat list of display strings (e.g. `['Generic Admin',
     *     'Kick', 'Ban']`). Preserves `SmFlagsToSb()`'s legacy
     *     contract so the parallel admin-list / admin-edit surfaces
     *     keep their existing wire shape.
     */
    public function __construct(
        public readonly bool $srvpwset,
        public readonly string $email,
        public readonly int $user_aid,
        public readonly array $web_permissions_grouped,
        public readonly false|array $server_permissions,
        public readonly int $min_pass_len,
    ) {
    }
}
