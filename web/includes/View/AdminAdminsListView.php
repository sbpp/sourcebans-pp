<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "List admins" section on the admin admins page — binds to
 * `page_admin_admins_list.tpl`.
 *
 * Permission booleans follow the {@see Perms::for()} `can_<flag>` naming
 * (owner bypass baked in). `admin.admins.php` passes them by name rather
 * than splatting `...Perms::for($userbank)` because the helper's
 * `array<string,bool>` return type doesn't carry constant-key shape, so
 * PHPStan can't prove the splat fills these named params and reports
 * `argument.missing` — see the rationale comment on the `Renderer::render`
 * call site.
 *
 * #1275 — page-level ToC removed in favour of Pattern A `?section=…`
 * routing. Pre-#1275 this View also carried `toc_id` / `toc_label` /
 * `toc_entries` because `page_admin_admins_list.tpl` `{include
 * file="page_toc.tpl"}` and `SmartyTemplateRule` walks includes
 * transitively. The unification on Pattern A (#1275) makes the page
 * render exactly one section per request via `AdminTabs`, so the
 * cross-template ToC + its three properties are gone.
 */
final class AdminAdminsListView extends View
{
    public const TEMPLATE = 'page_admin_admins_list.tpl';

    /**
     * @param list<array<string,mixed>> $admins Each entry is a row from
     *     `:prefix_admins` augmented by admin.admins.php with display
     *     fields (`user`, `name`, `aid`, `bancount`, `nodemocount`,
     *     `web_group`, `server_group`, `web_flag_string`,
     *     `server_flag_string`, `immunity`, `lastvisit`).
     */
    public function __construct(
        public readonly bool $can_list_admins,
        public readonly bool $can_add_admins,
        public readonly bool $can_edit_admins,
        public readonly bool $can_delete_admins,
        public readonly int $admin_count,
        public readonly string $admin_nav,
        public readonly array $admins,
    ) {
    }
}
