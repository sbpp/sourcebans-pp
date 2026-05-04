<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Group ban" tab on the admin bans page — binds to
 * `page_admin_bans_groups.tpl`.
 */
final class AdminBansGroupsView extends View
{
    public const TEMPLATE = 'page_admin_bans_groups.tpl';

    public function __construct(
        public readonly bool $permission_addban,
        public readonly bool $groupbanning_enabled,
        public readonly false|string $list_steam_groups,
    ) {
    }
}
