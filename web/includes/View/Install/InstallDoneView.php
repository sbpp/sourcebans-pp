<?php
declare(strict_types=1);

namespace Sbpp\View\Install;

use Sbpp\View\View;

/**
 * Step 5 (done half) of the install wizard — final success page.
 *
 * Pair: web/install/pages/page.5.php +
 * web/themes/default/install/page_done.tpl.
 *
 * Rendered after the page handler successfully writes config.php,
 * runs data.sql, and creates the admin row.
 *
 * `$config_writable` flips the "we wrote your config" copy vs. the
 * "paste this into config.php yourself" fallback. `$config_text`
 * is the fallback content.
 *
 * `$databases_cfg` is the SourceMod databases.cfg snippet for the
 * operator to paste into their gameserver.
 *
 * `$show_local_warning` flips on if the operator entered
 * `localhost` for the DB host — surfaces a copy explaining that
 * gameservers may need a remote-routable hostname.
 */
final class InstallDoneView extends View
{
    public const TEMPLATE = 'install/page_done.tpl';

    public function __construct(
        public readonly string $page_title,
        public readonly int $step,
        public readonly string $step_title,
        public readonly int $step_count,
        public readonly string $step_label,
        public readonly bool $config_writable,
        public readonly string $config_text,
        public readonly string $databases_cfg,
        public readonly bool $show_local_warning,
        public readonly string $val_server,
        public readonly string $val_port,
        public readonly string $val_username,
        public readonly string $val_password,
        public readonly string $val_database,
        public readonly string $val_prefix,
    ) {
    }
}
