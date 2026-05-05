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
 */
final class YourAccountView extends View
{
    public const TEMPLATE = 'page_youraccount.tpl';

    /**
     * @param false|list<string> $web_permissions    `false` when the admin
     *     has no web permissions, otherwise the list of display strings.
     * @param false|list<string> $server_permissions Same shape as above for
     *     server permissions.
     */
    public function __construct(
        public readonly bool $srvpwset,
        public readonly string $email,
        public readonly int $user_aid,
        public readonly false|array $web_permissions,
        public readonly false|array $server_permissions,
        public readonly int $min_pass_len,
    ) {
    }
}
