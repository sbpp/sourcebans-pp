<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Admin → Audit log page (#1123 B19).
 *
 * Binds to `page_admin_audit.tpl`. New page, no legacy template — backed
 * by the existing `:prefix_log` table (no schema change). Each
 * `audit_log` row is normalised by `web/pages/admin.audit.php` into the
 * shape the template iterates over: `tid` (PK from `lid`),
 * `severity` (one of `m`/`w`/`e`, mapped to `severity_label` +
 * `severity_class` for the badge), `time_human` + `time_iso` (formatted
 * from `created`), `actor` (admin name from `aid` join, or `"system"`
 * when the row was emitted by an unauthenticated path), `title`,
 * `detail` (from `message`), and `ip` (from `host`).
 *
 * SSR pagination + filtering: `current_severity`, `search`,
 * `current_page`, `page_count`, plus `prev_url` / `next_url` /
 * `has_prev` / `has_next` for the pager. We chose SSR over an
 * `audit.list` JSON action because the page is admin-only and low
 * traffic; form-based filter chips + GET pagination keep the page
 * usable without a JS dependency.
 */
final class AuditLogView extends View
{
    public const TEMPLATE = 'page_admin_audit.tpl';

    /**
     * @param list<array{
     *     tid: int,
     *     severity: string,
     *     severity_label: string,
     *     severity_class: string,
     *     time_human: string,
     *     time_iso: string,
     *     actor: string,
     *     title: string,
     *     detail: string,
     *     ip: string,
     * }> $audit_log
     */
    public function __construct(
        public readonly array $audit_log,
        public readonly int $total_count,
        public readonly string $current_severity,
        public readonly string $search,
        public readonly int $current_page,
        public readonly int $page_count,
        public readonly bool $has_prev,
        public readonly bool $has_next,
        public readonly string $prev_url,
        public readonly string $next_url,
    ) {
    }
}
