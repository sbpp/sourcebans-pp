<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\View;

use Sbpp\Config;

/**
 * Resolve the operator-configurable `template.logo` setting to a
 * theme-relative path that is guaranteed to exist on disk inside the
 * active theme directory. The chrome's brand-mark surfaces
 * (`core/navbar.tpl` sidebar mark + `page_login.tpl` sign-in card)
 * consume the result; the settings page deliberately renders the
 * raw configured value so the operator can see (and fix) a broken
 * pointer.
 *
 * The pre-fix shape was a direct
 * `Config::get('template.logo')` → `<img src="themes/<theme>/<value>">`
 * concatenation in both render paths. That ships a broken `<img>` on
 * three reachable input shapes:
 *
 *   1. **`logos/sb-large.png`** — the v1.x default. Was the value
 *      shipped by `web/install/includes/sql/data.sql` for years
 *      before #1235 wired the setting back into the chrome. Migration
 *      `web/updater/data/809.php` forward-converts existing installs
 *      whose row STILL matches the v1.x default, but it's a one-shot
 *      hop — an install that was on a v2.0 RC version that already
 *      had `config.version >= 810` when #1235 landed (so the
 *      migration's WHERE didn't gate but the row was never updated
 *      because the migration itself didn't exist yet) would silently
 *      skip the conversion and stay on the broken path. The file
 *      `themes/default/logos/sb-large.png` doesn't exist in v2.0 —
 *      it never shipped under the new default theme.
 *   2. **Empty / `null` / row missing** — the operator wiped the
 *      input field in `Admin → Settings → General → Logo path` and
 *      saved (the page's REPLACE INTO accepts an empty string), or
 *      an upgrade path lost the row. `Config::get` returns `null`,
 *      casting to string yields `""`, and the rendered `<img>`
 *      becomes `<img src="themes/default/">` — a broken image with
 *      no fallback.
 *   3. **Custom path that no longer exists** — admin set a value
 *      like `logos/my-server-logo.png`, then the file was removed
 *      from disk during a theme refresh / git checkout / typo'd
 *      filename. The chrome can't tell the difference between
 *      "operator hasn't customised" and "operator's customisation
 *      points at a deleted file" without checking disk.
 *
 * The fix is a single resolver consulted by both chrome render
 * paths. Resolution order:
 *
 *   1. If `template.logo` is null / empty / whitespace-only →
 *      {@see self::DEFAULT_PATH}.
 *   2. If the configured value contains `..` (path-traversal
 *      indicator), `\` (Windows-style separator), or `\0` (null
 *      byte — closes an admin-only chrome-DoS surface: PHP 8.0+
 *      `is_file()` throws `\ValueError` on null-byte paths, which
 *      pre-fix propagated past the resolver into the chrome's
 *      top-level error handler and 500'd every panel render until
 *      the row was rolled back) → {@see self::DEFAULT_PATH}. The
 *      current public ban-list export / single-demo download /
 *      theme-conf reader surfaces already sanitise admin-supplied
 *      paths against traversal; this resolver matches that posture
 *      so a custom `template.logo` can't smuggle the chrome into
 *      rendering arbitrary files outside the active theme tree.
 *   3. If the configured value matches the well-known v1.x default
 *      `logos/sb-large.png` (case-insensitive via {@see strcasecmp()}
 *      so a case-flipped variant `LOGOS/SB-LARGE.PNG` on a
 *      case-insensitive filesystem still gets rejected; defense-
 *      in-depth against the migration not having run) →
 *      {@see self::DEFAULT_PATH}.
 *   4. If the resolved on-disk path doesn't exist via
 *      {@see is_file()} → {@see self::DEFAULT_PATH}.
 *   5. Otherwise → the configured value (theme-relative, leading
 *      `/` stripped).
 *
 * The on-disk check uses {@see SB_THEMES} (an absolute filesystem
 * path defined by `init.php` as `ROOT . 'themes/'`) plus the active
 * theme name from `config.theme`. The active theme directory is
 * `SB_THEMES . <theme>/`. The brand-mark default
 * (`{@see self::DEFAULT_PATH}` = `'images/favicon.svg'`) ships in
 * `web/themes/default/images/favicon.svg` (the SourceBans++ shield
 * mark from the favicon set landed in #1235).
 *
 * Single source of truth — never inline the default path literal
 * anywhere else.
 *
 * The settings page (`page_admin_settings_settings.tpl`) deliberately
 * displays the raw configured value, not the resolved fallback —
 * otherwise an admin who set a bogus path would see "images/favicon.svg"
 * in the input and conclude their customisation was discarded. The
 * settings input is the operator-facing "what's saved"; this helper
 * is the chrome-facing "what gets rendered".
 *
 * @see Config::get for the underlying setting read
 * @see web/updater/data/809.php for the v1.x default forward-conversion
 * @see web/install/includes/sql/data.sql for the fresh-install seed
 * @see web/themes/default/core/navbar.tpl, web/themes/default/page_login.tpl
 *      for the two consumers
 */
final class BrandLogo
{
    /**
     * Default theme-relative path the chrome falls back to whenever
     * the configured `template.logo` is missing, empty, points at
     * the well-known v1.x default `logos/sb-large.png` (which never
     * shipped in the v2.0 default theme), or points at a file that
     * doesn't exist on disk.
     *
     * Matches the fresh-install seed in
     * `web/install/includes/sql/data.sql` and the migration target
     * in `web/updater/data/809.php`. If you change this constant,
     * change those two paired surfaces in the same commit.
     */
    public const DEFAULT_PATH = 'images/favicon.svg';

    /**
     * The v1.x default that never shipped in the v2.0 default
     * theme. Hardcoded rejection because `is_file()` against this
     * path already fails on the v2.0 default theme — we surface
     * this constant separately so a fork that DID ship a
     * `logos/sb-large.png` (some third-party themes carried over
     * the v1.x asset) can still see the chrome refuse to use it,
     * matching the operator-facing migration's intent to bury the
     * v1.x default.
     *
     * Public so the migration-pin regression test can reference it
     * directly instead of duplicating the literal (and so the
     * literal stays single-source between this constant and
     * `web/updater/data/809.php`'s WHERE clause). Comparison via
     * {@see strcasecmp()} so a case-flipped variant
     * (`LOGOS/SB-LARGE.PNG`) on a case-insensitive filesystem
     * still gets rejected — the migration's MariaDB-side
     * `utf8mb4_unicode_ci` collation already does case-insensitive
     * comparison on its own arm of the contract.
     */
    public const V1_DEFAULT_PATH = 'logos/sb-large.png';

    /**
     * Disallow instantiation — this class is a static helper namespace.
     */
    private function __construct()
    {
    }

    /**
     * Resolve `template.logo` to a theme-relative path that exists
     * on disk. The chrome's `core/navbar.tpl` consumes this through
     * `core/header.php`'s `$theme->assign('logo', …)`; the template
     * renders `<img src="{$theme_url}/{$logo}">`.
     *
     * @return string Theme-relative path (e.g. `images/favicon.svg`).
     *                Never empty; never starts with `/`.
     */
    public static function resolve(): string
    {
        $configured = trim((string) Config::get('template.logo'));
        if ($configured === '') {
            return self::DEFAULT_PATH;
        }

        // Strip a single leading slash for the same reason
        // `page.login.php` did pre-fix — the value is conceptually a
        // theme-relative path even when an admin types `/logos/foo.png`
        // expecting it to "start at the theme root".
        $relative = ltrim($configured, '/');
        if ($relative === '') {
            return self::DEFAULT_PATH;
        }

        // Defense against `..` traversal + Windows-style backslashes
        // + null-byte injection. An admin (whose only required
        // permission to write this setting is ADMIN_SETTINGS, NOT
        // owner) could type `../../../etc/passwd` in the settings
        // input; pre-fix the chrome happily rendered
        // `<img src="themes/default/../../../etc/passwd">`. The
        // resolver rejects the input outright and falls back to the
        // default so a forged path cannot escape the theme tree.
        //
        // The null-byte arm closes an admin-only chrome-DoS surface:
        // PHP 8.0+ `is_file()` throws `\ValueError` on a path
        // containing `\0` (per the PHP 8.0 null-byte filesystem-
        // function RFC). Without this rejection a payload like
        // `"images/foo.png\0extra"` propagates the exception past
        // `resolve()` and `core/header.php` into the chrome's
        // top-level error handler — surfacing a 500 on every
        // panel page render for every user (including the owner)
        // until the row is rolled back. Pinned by
        // `BrandLogoTest::testNullByteInPathFallsBackToShield`.
        if (
            str_contains($relative, '..')
            || str_contains($relative, '\\')
            || str_contains($relative, "\0")
        ) {
            return self::DEFAULT_PATH;
        }

        // Defense-in-depth: even if migration 809 didn't run for some
        // edge-case install, never render the v1.x default that
        // doesn't exist in the v2.0 default theme. `strcasecmp` so
        // a case-flipped variant (`LOGOS/SB-LARGE.PNG`) on a
        // case-insensitive filesystem (macOS HFS+, Windows NTFS,
        // ext4-mounted with `case_insensitive`) where a fork ships
        // the v1.x asset under any case can't slip past.
        if (strcasecmp($relative, self::V1_DEFAULT_PATH) === 0) {
            return self::DEFAULT_PATH;
        }

        // The active theme dir is `SB_THEMES . <theme>/`. SB_THEMES
        // is `ROOT . 'themes/'` (absolute filesystem path) per
        // init.php; `config.theme` is the operator-configurable
        // theme name. Cast through string + null-coalesce to handle
        // the rare case of `config.theme` being unset (fresh-install
        // seed sets it to `'default'`, but the test bootstrap
        // matches the panel's `?: 'default'` shape in
        // `web/init.php`).
        $themeName = (string) (Config::get('config.theme') ?: 'default');
        $themeRoot = self::themesRoot() . $themeName . DIRECTORY_SEPARATOR;
        $candidate = $themeRoot . $relative;

        if (!is_file($candidate)) {
            return self::DEFAULT_PATH;
        }

        return $relative;
    }

    /**
     * Resolve `template.logo` to a public URL suitable for `src=…`.
     * Joins the active theme's public-URL prefix
     * (`themes/<theme>/`) with the result of {@see self::resolve()}.
     *
     * The login page (`page.login.php`) uses this because the
     * `LoginView` carries a single `$brand_logo_url` property (the
     * View deliberately doesn't expose `$theme_url` + `$logo` as a
     * separate-pair so SmartyTemplateRule property↔reference parity
     * stays simple). The sidebar render path consumes the
     * theme-relative shape via {@see self::resolve()} because
     * `core/navbar.tpl` already has `{$theme_url}` in scope.
     *
     * @return string Public URL (e.g. `themes/default/images/favicon.svg`).
     */
    public static function resolveUrl(): string
    {
        $themeName = (string) (Config::get('config.theme') ?: 'default');
        return 'themes/' . $themeName . '/' . self::resolve();
    }

    /**
     * Returns the on-disk root directory containing all themes
     * (`web/themes/`). Wraps the {@see SB_THEMES} constant so tests
     * that don't bring `init.php` into scope can still exercise the
     * resolver against the test bootstrap's matching definition (the
     * PHPUnit bootstrap defines `SB_THEMES` to the same value
     * `init.php` does).
     *
     * Fail-closed when `SB_THEMES` is undefined: returning the empty
     * string would silently turn `is_file($themeName . '/' . $relative)`
     * into a CWD-relative check, which (a) could accidentally pass
     * if the panel happens to be invoked from a directory with a
     * matching layout (CLI tooling, custom entry points), and (b)
     * weakens the path-rooting contract the resolver advertises.
     * The constant is defined by `init.php` (and `tests/bootstrap.php`)
     * before any chrome render runs; reaching this branch means the
     * call site failed to bootstrap correctly, which is a programmer
     * error worth surfacing loudly. Mirrors the project's
     * "Fail closed" posture documented under "Public auth surfaces"
     * in AGENTS.md.
     */
    private static function themesRoot(): string
    {
        if (!defined('SB_THEMES')) {
            throw new \LogicException(
                'SB_THEMES must be defined before Sbpp\\View\\BrandLogo::resolve() is called. '
                . 'This constant is defined by web/init.php and web/tests/bootstrap.php; '
                . 'reaching this branch means the call site bypassed the panel bootstrap.'
            );
        }
        return (string) constant('SB_THEMES');
    }
}
