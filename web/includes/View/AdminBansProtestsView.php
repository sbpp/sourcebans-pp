<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Current protests sub-tab of the admin bans page — binds to
 * `page_admin_bans_protests.tpl`.
 */
final class AdminBansProtestsView extends View
{
    public const TEMPLATE = 'page_admin_bans_protests.tpl';

    /**
     * @param list<array<string,mixed>> $protest_list
     */
    public function __construct(
        public readonly bool $permission_protests,
        public readonly bool $permission_editban,
        public readonly string $protest_nav,
        public readonly array $protest_list,
        public readonly int $protest_count,
    ) {
    }
}
