<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Server admins" panel rendered by `pages/admin.srvadmins.php` — binds to
 * `page_admin_servers_adminlist.tpl`. Lists the admins with access to the
 * selected server (directly or through a server group) and, when the
 * SourceQuery probe succeeded, the live in-game name + IP for each one.
 *
 * Each row in `$admin_list` mirrors the shape `admin.srvadmins.php` builds:
 *
 *   - `user`   string|null — admin display name from `:prefix_admins`.
 *   - `authid` string|null — admin SteamID.
 *   - `ingame` bool        — true when the admin is currently connected.
 *   - `iname`  string|null — in-game name (only when `ingame` is true).
 *   - `iip`    string|null — in-game IP (only when `ingame` is true).
 */
final class AdminServersAdminListView extends View
{
    public const TEMPLATE = 'page_admin_servers_adminlist.tpl';

    /**
     * @param list<array{
     *     user: string|null,
     *     authid: string|null,
     *     ingame: bool,
     *     iname?: string|null,
     *     iip?: string|null,
     * }> $admin_list
     */
    public function __construct(
        public readonly int $admin_count,
        public readonly array $admin_list,
    ) {
    }
}
