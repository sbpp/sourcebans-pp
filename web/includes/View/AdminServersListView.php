<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * First tab of the admin "Servers" page — binds to
 * `page_admin_servers_list.tpl`.
 *
 * `pemission_delserver` keeps its historical spelling because the template
 * references `{if $pemission_delserver}`; renaming is a separate concern.
 */
final class AdminServersListView extends View
{
    public const TEMPLATE = 'page_admin_servers_list.tpl';

    /**
     * @param list<array<string,mixed>> $server_list
     */
    public function __construct(
        public readonly bool $permission_list,
        public readonly bool $permission_editserver,
        public readonly bool $pemission_delserver,
        public readonly bool $permission_addserver,
        public readonly int $server_count,
        public readonly array $server_list,
    ) {
    }
}
