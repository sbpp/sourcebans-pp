<?php
declare(strict_types=1);

namespace Sbpp\View\Install;

use Sbpp\View\View;

/**
 * Step 3 of the install wizard — environment requirements check.
 *
 * Pair: web/install/pages/page.3.php +
 * web/themes/default/install/page_requirements.tpl.
 *
 * `$rows` is a list of grouped requirement checks. Each row is a
 * (label, status, detail) triple where `status` is the literal
 * `'ok'` / `'warn'` / `'err'` so the template can switch on it
 * without parsing colour names.
 *
 * `$can_continue` mirrors `$errors === 0` from the page handler;
 * the template gates the "Continue" button on it.
 *
 * `$val_*` carries forward the DB credentials + Steam API key +
 * admin email so the next handoff to step 4 stays POST-driven.
 *
 * @phpstan-type RequirementGroup array{
 *     title: string,
 *     rows: list<array{label: string, status: string, required: string, detail: string}>
 * }
 */
final class InstallRequirementsView extends View
{
    public const TEMPLATE = 'install/page_requirements.tpl';

    /**
     * @param list<RequirementGroup> $groups
     */
    public function __construct(
        public readonly string $page_title,
        public readonly int $step,
        public readonly string $step_title,
        public readonly int $step_count,
        public readonly string $step_label,
        public readonly array $groups,
        public readonly bool $can_continue,
        public readonly int $errors,
        public readonly int $warnings,
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
