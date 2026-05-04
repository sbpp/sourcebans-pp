<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Your-account (self-service) page — binds to `page_youraccount.tpl`, which
 * is rendered with the custom `-{ … }-` delimiter pair (see
 * `page.youraccount.php`). The {@see View::DELIMITERS} override teaches
 * SmartyTemplateRule to scan for `-{$var}-` references instead of `{$var}`.
 */
final class YourAccountView extends View
{
    public const TEMPLATE = 'page_youraccount.tpl';

    /** @var array{0: string, 1: string} */
    public const DELIMITERS = ['-{', '}-'];

    /**
     * @param false|list<string> $web_permissions  `false` when the admin
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
