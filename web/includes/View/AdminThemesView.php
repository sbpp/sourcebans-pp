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
 * Variable shape:
 *
 *   - `$theme_list` is the only structural variable; both themes
 *     iterate it. Each row carries `dir`, `name`, `author`, `version`,
 *     `link`, `screenshot`, and `active` — the legacy default theme
 *     only reads `dir` + `name`, the sbpp2026 card grid uses every
 *     field.
 *   - `$theme_name`, `$theme_author`, `$theme_version`, `$theme_link`,
 *     `$theme_screenshot` describe the currently-selected theme;
 *     they exist for the legacy default-theme template's "current
 *     theme details" panel. The sbpp2026 template derives the same
 *     info from `$theme_list[].active`, so PHPStan baselines those
 *     scalars as "unused" on the sbpp2026 leg (and the dual-theme
 *     matrix's `reportUnmatchedIgnoredErrors=false` keeps that clean).
 *     `$theme_screenshot` is intentionally a pre-built `<img>` tag
 *     string for the legacy `{nofilter}` consumer; the sbpp2026 grid
 *     uses `$theme_list[].screenshot` (URL only) for its own `<img>`
 *     elements.
 *   - `$can_*`, `$active_section`, `$current_theme_dir` are sbpp2026-only
 *     and ride the same baseline pattern (default-theme leg sees
 *     them as unused).
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
        public readonly string $active_section,
        public readonly string $current_theme_dir,
    ) {
    }
}
