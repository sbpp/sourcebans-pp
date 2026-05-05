<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Per-server RCON console — binds to `page_admin_servers_rcon.tpl`, which
 * is rendered with the custom `-{ … }-` delimiter pair (see
 * `pages/admin.rcon.php`). The {@see View::DELIMITERS} override teaches
 * SmartyTemplateRule to scan for `-{$var}-` references instead of `{$var}`.
 *
 * The console itself talks to `Actions.ServersSendRcon`; the page only
 * needs the target `$id` (server sid) and a precomputed
 * `$permission_rcon` flag combining `SM_RCON|SM_ROOT` with the per-server
 * access loop in `admin.rcon.php`.
 */
final class AdminServersRconView extends View
{
    public const TEMPLATE = 'page_admin_servers_rcon.tpl';

    /** @var array{0: string, 1: string} */
    public const DELIMITERS = ['-{', '}-'];

    public function __construct(
        public readonly int $id,
        public readonly bool $permission_rcon,
    ) {
    }
}
