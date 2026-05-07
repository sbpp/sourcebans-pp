<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * "Themes" sub-tab of the admin settings page — binds to
 * `page_admin_settings_themes.tpl`. Renders a card grid of every
 * directory under `web/themes/` that ships a `theme.conf.php`.
 *
 * The card "Use this theme" button calls
 * {@see Actions.SystemApplyTheme} (action `system.apply_theme`,
 * gated `ADMIN_OWNER | ADMIN_WEB_SETTINGS` in `_register.php`); on
 * success the JSON envelope's `reload: true` flag triggers a full
 * page reload so the new chrome paints with the right CSS bundle.
 *
 * #1259 dropped `$active_section` from this View. Section navigation
 * (the Settings sidebar) lives in `core/admin_sidebar.tpl` driven by
 * `web/includes/AdminTabs.php`; the active link is resolved from the
 * partial's `$active_tab` variable, not from a per-View property the
 * template re-reads. AGENTS.md "Sub-paged admin routes" carries the
 * convention.
 *
 * Variable shape:
 *
 *   - `$theme_list` is the structural variable. Each row carries
 *     `dir`, `name`, `author`, `version`, `link`, `screenshot`, and
 *     `active`; the card grid uses every field.
 *   - `$can_*`, `$current_theme_dir` drive per-card affordances.
 *   - `$theme_name`, `$theme_author`, `$theme_version`, `$theme_link`,
 *     `$theme_screenshot` describe the currently-selected theme as
 *     standalone scalars. They are preserved here for any third-party
 *     theme that forked the pre-v2.0.0 default and renders a "current
 *     theme" details panel; the shipped card grid derives the same
 *     info from `$theme_list[].active`. `$theme_screenshot` is
 *     intentionally a pre-built `<img>` tag string for the legacy
 *     `{nofilter}` consumer.
 */
final class AdminThemesView extends View
{
    public const TEMPLATE = 'page_admin_settings_themes.tpl';

    /**
     * @param list<array{
     *     dir: string,
     *     name: string,
     *     author: string,
     *     version: string,
     *     link: string,
     *     screenshot: string,
     *     active: bool
     * }> $theme_list
     */
    public function __construct(
        public readonly array $theme_list,
        public readonly string $theme_name,
        public readonly string $theme_author,
        public readonly string $theme_version,
        public readonly string $theme_link,
        public readonly string $theme_screenshot,
        public readonly bool $can_web_settings,
        public readonly bool $can_owner,
        public readonly string $current_theme_dir,
    ) {
    }
}
