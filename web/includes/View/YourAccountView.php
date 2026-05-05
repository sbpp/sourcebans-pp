<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Your-account (self-service) page — binds to `page_youraccount.tpl`.
 *
 * Historical note: prior to #1123 B20 this view rendered the legacy
 * `default/page_youraccount.tpl`, which used the non-default `-{ … }-`
 * Smarty delimiter pair (a SourceBans-1.x convention; see
 * `web/pages/page.youraccount.php` history). The View overrode
 * {@see View::DELIMITERS} so SmartyTemplateRule could parse the
 * `-{$var}-` references; the page handler also had to swap delimiters
 * on the live `$theme` before and after `display()`. The B20 redesign
 * rewrites the template under `web/themes/sbpp2026/` in **standard**
 * `{ }` Smarty delimiters so neither workaround is needed.
 *
 * The legacy `web/themes/default/page_youraccount.tpl` stays on disk
 * with its `-{ }-` markers until D1 deletes the entire `default/`
 * directory; SmartyTemplateRule's tag regex matches the inner `{$var}`
 * inside each `-{$var}-` so the dual-theme PHPStan matrix's "default"
 * leg keeps finding every property here as "referenced". No baseline
 * entry is needed for that interim window.
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
