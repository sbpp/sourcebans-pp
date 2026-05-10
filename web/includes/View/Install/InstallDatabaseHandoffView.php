<?php
declare(strict_types=1);

namespace Sbpp\View\Install;

use Sbpp\View\View;

/**
 * Auto-submit handoff page rendered between wizard steps where the
 * server-side handler validated POST data and now needs to forward
 * it (still as POST) to the next step.
 *
 * The page is a noscript-friendly form with a "Continue" button +
 * a tiny page-tail submit-on-load script. It exists because the
 * wizard carries DB credentials + admin form data between steps
 * via hidden POST fields (no `$_SESSION`; see InstallDatabaseView's
 * docblock for the rationale), and a 302 redirect can't carry POST.
 *
 * Used by:
 *   - step 2 → step 3 (DB credentials).
 *   - step 4 → step 5 (DB credentials + charset).
 *   - step 5 → step 6 (no payload — final transition; see
 *     InstallFinalView for the actual write-config flow).
 */
final class InstallDatabaseHandoffView extends View
{
    public const TEMPLATE = 'install/page_handoff.tpl';

    public function __construct(
        public readonly string $page_title,
        public readonly int $step,
        public readonly string $step_title,
        public readonly int $step_count,
        public readonly string $step_label,
        public readonly int $next_step,
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
