<?php
declare(strict_types=1);

namespace Sbpp\View;

use Sbpp\Auth\UserManager;
use Sbpp\Config;

/**
 * Build the permission-filtered navigation set the command palette
 * (Ctrl/Cmd-K, `<dialog id="palette-root">`) renders into its
 * "Navigate" section.
 *
 * Issue #1304: pre-fix, the palette's nav set was a hardcoded
 * `NAV_ITEMS` array in `web/themes/default/js/theme.js` with no
 * permission filter — every visitor (logged-out, partial-permission
 * admins) saw the admin-only entries (`Admin panel`, `Add ban`)
 * alongside the public ones, then got bounced off the "you must be
 * logged in" / 403 surface when they clicked one. The sibling sidebar
 * (`web/pages/core/navbar.php` + `core/navbar.tpl`) already built a
 * filtered link set; the palette didn't.
 *
 * The fix server-renders the filtered set as a JSON blob inside a
 * `<script type="application/json" id="palette-actions">` block in
 * `core/footer.tpl`; `theme.js` reads `JSON.parse` of that blob at
 * boot and uses it instead of the hardcoded array. The player search
 * half (`bans.search`) is intentionally public and stays unchanged —
 * the leak was strictly the navigation entries.
 *
 * Each entry carries:
 *   - `icon`        Lucide icon name rendered into the row.
 *   - `label`       Display text the palette filters by case-insensitive
 *                   substring match.
 *   - `href`        Navigation target. Anchors clicked / Enter-fired
 *                   from a keyboard-focused palette row land here.
 *   - `permission`  Either an `int` bitmask (admin entries — `HasAccess`
 *                   gate) or `true`/`false`/`null` for "always shown"
 *                   public entries. The `int` shape matches the legacy
 *                   `ADMIN_OWNER | ADMIN_ADD_BAN` form already in use
 *                   throughout `web/pages/core/navbar.php`. Owner is OR'd
 *                   in by the caller via the `ADMIN_OWNER | …` shape, so
 *                   the bitmask itself documents the gate at the call site.
 *   - `config`      Optional `sb_settings` boolean key — entry is dropped
 *                   when the bool is false. Mirrors `navbar.php`'s
 *                   `Config::getBool('config.enablecomms')` etc. so the
 *                   palette agrees with the sidebar on which public
 *                   surfaces are reachable for this install.
 *
 * The output shape (after filtering) drops `permission` / `config` so
 * the JSON blob carries only the three keys `theme.js` consumes —
 * smaller wire payload, less DOM noise, and no temptation to use
 * `permission` as a client-side gate (the gate is server-side,
 * full stop).
 *
 * Anti-leakage discipline (mirrors `navbar.php`):
 *   - `is_logged_in()` short-circuits ALL admin entries. A null userbank
 *     (CSRF reject path / unhandled error) is treated identically to
 *     logged-out.
 *   - Admin entries OR `ADMIN_OWNER` into their permission mask so an
 *     owner sees every entry without the catalog having to track the
 *     bypass — same convention as `CheckAdminAccess` everywhere else.
 *
 * Adding a new palette entry:
 *   - Drop a new row into `entries()` below.
 *   - For admin entries, name the matching `web/configs/permissions/web.json`
 *     flag (OR'd with `ADMIN_OWNER`); the `PaletteActionsTest` regression
 *     suite asserts the entry stays gated.
 *   - For public entries, decide whether the install can disable the
 *     surface via a `config.enable*` toggle and add the `config` key
 *     if so. Without `config`, the entry shows for every install.
 *
 * The entry list intentionally matches the pre-#1304 hardcoded JS
 * array's ordering (Dashboard → Servers → Banlist → Comms → Submit →
 * Appeals → Admin → Add ban) so the visible behaviour for an owner
 * is byte-identical to the pre-fix rendering — only the leak is
 * gone.
 */
final class PaletteActions
{
    /**
     * Disallow instantiation — this class is a static helper namespace.
     */
    private function __construct()
    {
    }

    /**
     * Build the public output shape for $userbank. Each entry is an
     * `{icon, label, href}` map suitable for direct JSON encoding into
     * the `<script type="application/json" id="palette-actions">` blob.
     *
     * @return list<array{icon: string, label: string, href: string}>
     */
    public static function for(?UserManager $userbank): array
    {
        $isLoggedIn = $userbank !== null && $userbank->is_logged_in();
        $owner = defined('ADMIN_OWNER') ? (int) constant('ADMIN_OWNER') : 0;

        $out = [];
        foreach (self::entries() as $entry) {
            // Optional `config.enable*` toggle. Mirrors navbar.php so a
            // disabled surface (e.g. config.enablecomms = 0) drops out
            // of both the sidebar and the palette in the same request.
            if (isset($entry['config']) && !Config::getBool($entry['config'])) {
                continue;
            }

            $perm = $entry['permission'];
            // The catalog's permission shape is `bool|int|null`:
            //   - `true` / `null` → public, always shown
            //   - `false`         → disabled in source (kept for symmetry with
            //                       navbar.php's literal-false branch; no entry
            //                       currently uses it but keeping the case
            //                       avoids a silent leak if a future entry
            //                       drops the value back in)
            //   - `int`           → admin entry, must be logged in AND hold
            //                       the mask. `HasAccess` itself short-circuits
            //                       on aid <= 0, but the explicit
            //                       `is_logged_in()` guard documents the intent
            //                       at the filter site (matches navbar.php).
            //
            // The `match (true)` here pairs the discriminator with the
            // resulting bool in one expression, and is exhaustive over the
            // catalog's declared shape — PHPStan can prove that the `int`
            // arm is reached on the int branch (which is why the previous
            // `is_int($perm)` guard tripped `function.alreadyNarrowedType`).
            // `$isLoggedIn` already implies `$userbank !== null`, so the
            // explicit non-null guard from before was redundant too
            // (`notIdentical.alwaysTrue`).
            $allowed = match (true) {
                $perm === true, $perm === null => true,
                $perm === false => false,
                default => $isLoggedIn && $userbank->HasAccess($perm | $owner),
            };

            if (!$allowed) {
                continue;
            }

            $out[] = [
                'icon'  => $entry['icon'],
                'label' => $entry['label'],
                'href'  => $entry['href'],
            ];
        }

        return $out;
    }

    /**
     * The catalog. New entries land here; `for()` does the filtering.
     *
     * The shape is `array{icon, label, href, permission, config?}`
     * declared inline rather than as a `@phpstan-type` because PHPStan's
     * level-5 array shape inference reads the literal here directly.
     *
     * @return list<array{
     *     icon: string,
     *     label: string,
     *     href: string,
     *     permission: bool|int|null,
     *     config?: string,
     * }>
     */
    private static function entries(): array
    {
        // Using `defined()` for the ADMIN_* constants so the catalog
        // is safely loadable from contexts (PHPStan analysis,
        // PHPUnit bootstrap variants) where init.php's permission
        // define() pass hasn't run yet. At runtime in the panel,
        // init.php has already populated them; in tests/bootstrap.php
        // pulls in web.json and define()s them too.
        $addBan = (defined('ADMIN_OWNER') ? (int) constant('ADMIN_OWNER') : 0)
            | (defined('ADMIN_ADD_BAN') ? (int) constant('ADMIN_ADD_BAN') : 0);

        return [
            [
                'icon'       => 'layout-dashboard',
                'label'      => 'Dashboard',
                'href'       => '?',
                'permission' => true,
            ],
            [
                'icon'       => 'server',
                'label'      => 'Servers',
                'href'       => '?p=servers',
                'permission' => true,
            ],
            [
                'icon'       => 'ban',
                'label'      => 'Ban list',
                'href'       => '?p=banlist',
                'permission' => true,
            ],
            [
                'icon'       => 'mic-off',
                'label'      => 'Comm blocks',
                'href'       => '?p=commslist',
                'permission' => true,
                'config'     => 'config.enablecomms',
            ],
            [
                'icon'       => 'flag',
                'label'      => 'Submit a ban',
                'href'       => '?p=submit',
                'permission' => true,
                'config'     => 'config.enablesubmit',
            ],
            [
                'icon'       => 'megaphone',
                'label'      => 'Appeals',
                'href'       => '?p=protest',
                'permission' => true,
                'config'     => 'config.enableprotest',
            ],
            // Admin landing — gated on ALL_WEB to mirror page-builder.php's
            // CheckAdminAccess(ALL_WEB) on the bare admin landing route.
            // Any admin (any web flag) reaches the page; non-admins (no
            // web flags) are kept off the palette so they don't hit
            // "you must be logged in / 403" surfaces from the chrome.
            [
                'icon'       => 'shield',
                'label'      => 'Admin panel',
                'href'       => '?p=admin',
                'permission' => defined('ALL_WEB') ? (int) constant('ALL_WEB') : 0,
            ],
            // Add ban — same gate as the sub-route's CheckAdminAccess in
            // page-builder.php: ADMIN_OWNER | ADMIN_ADD_BAN.
            [
                'icon'       => 'plus-circle',
                'label'      => 'Add ban',
                'href'       => '?p=admin&c=bans&section=add-ban',
                'permission' => $addBan,
            ],
        ];
    }
}
