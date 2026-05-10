<?php
declare(strict_types=1);

namespace Sbpp\View\Install;

use Sbpp\View\View;

/**
 * Step 4 of the install wizard — schema install.
 *
 * Pair: web/install/pages/page.4.php +
 * web/themes/default/install/page_schema.tpl.
 *
 * The handler runs `struc.sql` against the validated DB on entry,
 * then this view renders the result. `$success` is the gate for
 * the "Continue" button; `$errors_text` is a one-liner shown in
 * the failure alert.
 *
 * `$charset` is the resolved charset (`utf8mb4` for MySQL/MariaDB
 * versions that support it, `utf8` otherwise). It carries forward
 * to step 5 so the config write knows what to bake in.
 */
final class InstallSchemaView extends View
{
    public const TEMPLATE = 'install/page_schema.tpl';

    public function __construct(
        public readonly string $page_title,
        public readonly int $step,
        public readonly string $step_title,
        public readonly int $step_count,
        public readonly string $step_label,
        public readonly bool $success,
        public readonly string $errors_text,
        public readonly int $tables_created,
        public readonly string $charset,
        public readonly string $val_server,
        public readonly string $val_port,
        public readonly string $val_username,
        public readonly string $val_password,
        public readonly string $val_database,
        public readonly string $val_prefix,
        public readonly string $val_apikey,
        public readonly string $val_email,
    ) {
    }
}
