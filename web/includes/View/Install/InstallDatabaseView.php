<?php
declare(strict_types=1);

namespace Sbpp\View\Install;

use Sbpp\View\View;

/**
 * Step 2 of the install wizard — DB connection + Steam API key
 * + admin email.
 *
 * Pair: web/install/pages/page.2.php +
 * web/themes/default/install/page_database.tpl.
 *
 * The form is the only route by which the wizard collects DB
 * credentials; everything downstream (steps 3–5) carries them as
 * hidden POST fields. We deliberately don't put the credentials in
 * `$_SESSION` — the install wizard is a pre-install flow with no DB
 * to anchor a session against, and a session-based handoff would
 * silently leak credentials into the operator's tmp dir if they
 * abandoned the install half-way.
 *
 * Pre-fill comes from the previous POST when validation failed, so
 * a typo in the prefix doesn't force the operator to retype the
 * whole connection string.
 *
 * `$error` carries a one-line error message for failed POST attempts
 * (empty string means "first GET" or "valid POST about to redirect"
 * — neither path renders this template).
 */
final class InstallDatabaseView extends View
{
    public const TEMPLATE = 'install/page_database.tpl';

    public function __construct(
        public readonly string $page_title,
        public readonly int $step,
        public readonly string $step_title,
        public readonly int $step_count,
        public readonly string $step_label,
        public readonly string $error,
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
