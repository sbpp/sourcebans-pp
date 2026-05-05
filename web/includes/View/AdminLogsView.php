<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "System log" sub-tab of the admin settings page — binds to
 * `page_admin_settings_logs.tpl`. Lists rows from `:prefix_log` with
 * inline expandable detail per row.
 *
 * Only `ADMIN_OWNER` can truncate the log table; `$can_owner` gates
 * the "Clear log" button in the sbpp2026 template. The legacy default
 * theme reads the prebuilt `$clear_logs` HTML link instead, so both
 * variables sit on the View together — `SmartyTemplateRule`'s
 * cross-check lets the dual-theme matrix accept either.
 */
final class AdminLogsView extends View
{
    public const TEMPLATE = 'page_admin_settings_logs.tpl';

    /**
     * @param list<array<string,mixed>> $log_items
     */
    public function __construct(
        public readonly string $clear_logs,
        public readonly string $page_numbers,
        public readonly array $log_items,
        public readonly bool $can_web_settings,
        public readonly bool $can_owner,
        public readonly string $active_section,
    ) {
    }
}
