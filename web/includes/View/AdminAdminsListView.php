<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "List admins" tab on the admin admins page — binds to
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
 * The View also carries the page-level ToC payload (`$toc_id`,
 * `$toc_label`, `$toc_entries`) because `page_admin_admins_list.tpl`
 * `{include file="page_toc.tpl"}` and the SmartyTemplateRule walks
 * includes transitively — so the includee's variables must come from
 * the parent template's scope, i.e. this View. See
 * `web/themes/default/page_toc.tpl` for the partial's contract and
 * `AGENTS.md` "Page-level table of contents (dense admin pages)" for
 * the broader pattern.
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
     * @param list<array{slug: string, label: string, icon: string}> $toc_entries
     *     Permission-filtered list of ToC anchors. Each entry's `slug`
     *     matches the `id="…"` on the `<section>` it points at; the
     *     handler must omit entries the dispatcher wouldn't paint so a
     *     ToC click never targets a non-existent anchor. The `icon`
     *     is a Lucide glyph name (e.g. "users", "shield"); #1266
     *     unified the chrome with Pattern A's iconed pill rows.
     */
    public function __construct(
        public readonly bool $can_list_admins,
        public readonly bool $can_add_admins,
        public readonly bool $can_edit_admins,
        public readonly bool $can_delete_admins,
        public readonly int $admin_count,
        public readonly string $admin_nav,
        public readonly array $admins,
        public readonly string $toc_id,
        public readonly string $toc_label,
        public readonly array $toc_entries,
    ) {
    }
}
