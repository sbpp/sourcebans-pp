<?php
declare(strict_types=1);

namespace Sbpp\View\Install;

use Sbpp\View\View;

/**
 * Step 5 (form half) of the install wizard — admin account form.
 *
 * Pair: web/install/pages/page.5.php +
 * web/themes/default/install/page_admin.tpl.
 *
 * `$error` carries a one-line error message for failed POST attempts
 * (empty string means "first GET — render empty form"). Pre-fill
 * shape mirrors InstallDatabaseView.
 */
final class InstallAdminView extends View
{
    public const TEMPLATE = 'install/page_admin.tpl';

    public function __construct(
        public readonly string $page_title,
        public readonly int $step,
        public readonly string $step_title,
        public readonly int $step_count,
        public readonly string $step_label,
        public readonly string $error,
        public readonly string $val_uname,
        public readonly string $val_steam,
        public readonly string $val_email,
        public readonly string $val_server,
        public readonly string $val_port,
        public readonly string $val_username,
        public readonly string $val_password,
        public readonly string $val_database,
        public readonly string $val_prefix,
        public readonly string $val_apikey,
        public readonly string $val_sb_email,
        public readonly string $val_charset,
    ) {
    }
}
