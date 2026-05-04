<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Add a ban" tab on the admin bans page — binds to `page_admin_bans_add.tpl`.
 */
final class AdminBansAddView extends View
{
    public const TEMPLATE = 'page_admin_bans_add.tpl';

    /**
     * @param false|list<string> $customreason `false` when custom reasons
     *     are disabled, otherwise the list of reason strings.
     */
    public function __construct(
        public readonly bool $permission_addban,
        public readonly false|array $customreason,
    ) {
    }
}
