<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Second tab of the admin "Servers" page (add / edit form) — binds to
 * `page_admin_servers_add.tpl`. Also used by the edit flow to prefill the
 * form; `edit_server` flips the template into edit mode.
 */
final class AdminServersAddView extends View
{
    public const TEMPLATE = 'page_admin_servers_add.tpl';

    /**
     * @param list<array{mid: int|string, name: string}> $modlist
     * @param list<array{gid: int|string, name: string}> $grouplist
     */
    public function __construct(
        public readonly bool $permission_addserver,
        public readonly bool $edit_server,
        public readonly string $ip,
        public readonly string $port,
        public readonly string $rcon,
        public readonly string $modid,
        public readonly array $modlist,
        public readonly array $grouplist,
        public readonly string $submit_text,
    ) {
    }
}
