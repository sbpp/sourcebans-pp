<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Advanced search" panel for the admin System Log sub-tab — binds to
 * `box_admin_log_search.tpl`.
 *
 * Rendered inline from `web/pages/admin.log.search.php`, which is itself
 * pulled in by the consumer page (`page_admin_settings_logs.tpl`) via
 * the `{load_template file="admin.log.search"}` Smarty plugin.
 *
 * The submitted search form is a plain `GET` to `?p=admin&c=settings`
 * with `section=logs`, `advSearch=<value>` and `advType=<key>` query
 * params, mirroring the legacy `search_log()` URL shape that
 * `web/pages/admin.settings.php` already parses (see `$_GET['advSearch']`
 * + `$_GET['advType']` in the `section === 'logs'` branch). No CSRF
 * field is needed: state is not mutated by the search.
 */
final class AdminLogSearchView extends View
{
    public const TEMPLATE = 'box_admin_log_search.tpl';

    /**
     * @param list<array<string,mixed>> $admin_list Each entry is a row
     *     from `:prefix_admins` with at minimum `aid` and `user`.
     */
    public function __construct(
        public readonly array $admin_list,
    ) {
    }
}
