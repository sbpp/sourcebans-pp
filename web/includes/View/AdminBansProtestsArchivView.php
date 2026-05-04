<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Archived protests sub-tab of the admin bans page — binds to
 * `page_admin_bans_protests_archiv.tpl`.
 */
final class AdminBansProtestsArchivView extends View
{
    public const TEMPLATE = 'page_admin_bans_protests_archiv.tpl';

    /**
     * @param list<array<string,mixed>> $protest_list_archiv
     */
    public function __construct(
        public readonly bool $permission_protests,
        public readonly bool $permission_editban,
        public readonly string $aprotest_nav,
        public readonly array $protest_list_archiv,
        public readonly int $protest_count_archiv,
    ) {
    }
}
