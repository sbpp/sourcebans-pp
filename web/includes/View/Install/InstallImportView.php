<?php
declare(strict_types=1);

namespace Sbpp\View\Install;

use Sbpp\View\View;

/**
 * Step 6 of the install wizard — optional AMXBans import.
 *
 * Pair: web/install/pages/page.6.php +
 * web/themes/default/install/page_import.tpl.
 *
 * Two-phase like step 5: GET renders the form, POST runs the
 * import + renders the same template with `$result_text` populated.
 *
 * The wizard preserves the new SourceBans++ DB credentials in
 * hidden fields (`val_*`) so a failed import attempt can be retried
 * against the same target without bouncing back to step 5.
 */
final class InstallImportView extends View
{
    public const TEMPLATE = 'install/page_import.tpl';

    public function __construct(
        public readonly string $page_title,
        public readonly int $step,
        public readonly string $step_title,
        public readonly int $step_count,
        public readonly string $step_label,
        public readonly string $error,
        public readonly string $result_text,
        public readonly string $val_amx_server,
        public readonly string $val_amx_port,
        public readonly string $val_amx_username,
        public readonly string $val_amx_database,
        public readonly string $val_amx_prefix,
        public readonly string $val_server,
        public readonly string $val_port,
        public readonly string $val_username,
        public readonly string $val_password,
        public readonly string $val_database,
        public readonly string $val_prefix,
    ) {
    }
}
