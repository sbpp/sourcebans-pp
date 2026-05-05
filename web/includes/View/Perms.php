<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Snapshot the current user's web-permission flags as a flat
 * `can_*` => bool array suitable for splatting into a {@see View}
 * subclass's named constructor.
 *
 * The convention each `Sbpp\View\<Page>View` follows:
 *
 *   1. Each permission the template needs is a constructor-promoted
 *      `public readonly bool $can_<flag>` property (e.g.
 *      `$can_add_ban`, `$can_edit_all_bans`, `$can_owner`).
 *   2. The page handler builds the View like:
 *
 *      ```php
 *      use Sbpp\View\Perms;
 *      use Sbpp\View\Renderer;
 *
 *      Renderer::render($theme, new BanListView(
 *          ...Perms::for($userbank),
 *          ban_list: $bans,
 *          // …
 *      ));
 *      ```
 *
 *      PHP discards splatted `can_*` keys the View doesn't declare, so
 *      a View only opts in to the booleans its template actually
 *      consumes — `SmartyTemplateRule` keeps that opt-in honest by
 *      flagging unused properties.
 *   3. Templates render `{if $can_add_ban} … {/if}` — never raw
 *      `{if $user.web_flags & …}` — and never need to know the
 *      bitmask values.
 *
 * The boolean set is derived at runtime by enumerating every
 * `ADMIN_*` constant defined by `init.php` from
 * `web/configs/permissions/web.json`. Adding a new permission flag in
 * that JSON automatically grows the array next request — no
 * maintenance burden here.
 *
 * Naming convention: each constant `ADMIN_FOO_BAR` becomes the array
 * key `can_foo_bar`. The `ADMIN_` prefix is stripped and the rest is
 * lowercased, matching the `{if $can_foo_bar}` style the templates use.
 *
 * For the rare case a template needs an ad-hoc check the View couldn't
 * precompute (e.g. inside a `{foreach}` over admins, gating one column
 * per row), use the paired `{has_access flag=$smarty.const.ADMIN_FOO}`
 * Smarty block plugin in `SmartyCustomFunctions.php`.
 */
final class Perms
{
    /**
     * Disallow instantiation — this class is a static helper namespace.
     */
    private function __construct()
    {
    }

    /**
     * Build the `['can_<flag>' => bool, …]` snapshot for $userbank.
     *
     * Returns all-false (every `can_*` => false) when:
     *   - $userbank is null (rare; CSRF/error pages reach the View
     *     without a bound user manager), or
     *   - the user is not logged in (`HasAccess()` short-circuits to
     *     false on aid <= 0, so the loop below converges on false for
     *     every flag without a special case).
     *
     * Owner bypass: every per-flag check is OR'd with `ADMIN_OWNER`
     * before being passed to `HasAccess()`, mirroring the convention
     * baked into `CheckAdminAccess(ADMIN_OWNER|…)` in
     * `web/includes/page-builder.php` and `system-functions.php`.
     * The effect is that a user holding the `ADMIN_OWNER` bit gets
     * `can_*` => true for every flag — templates can write
     * `{if $can_add_ban}` without remembering to also check
     * `$can_owner`. The standalone `can_owner` key is still emitted so
     * templates that want to gate strictly on the owner bit (e.g. a
     * "transfer ownership" panel) can do so explicitly.
     *
     * @return array<string, bool>
     */
    public static function for(?\CUserManager $userbank): array
    {
        $perms = [];
        $owner = defined('ADMIN_OWNER') ? (int) constant('ADMIN_OWNER') : 0;
        foreach (self::adminFlags() as $name => $value) {
            $key = strtolower(substr($name, strlen('ADMIN_')));
            $mask = $value | $owner;
            $perms['can_' . $key] = $userbank !== null
                && (bool) $userbank->HasAccess($mask);
        }
        return $perms;
    }

    /**
     * Enumerate every currently-defined `ADMIN_*` constant from the
     * `user` bucket. `init.php` populates this set at bootstrap from
     * `web/configs/permissions/web.json`; this getter is therefore
     * future-proof against new flags being added to that JSON.
     *
     * @return array<string, int>
     */
    private static function adminFlags(): array
    {
        $flags = [];
        $defined = get_defined_constants(true);
        $userBucket = $defined['user'] ?? [];
        foreach ($userBucket as $name => $value) {
            if (!str_starts_with($name, 'ADMIN_')) {
                continue;
            }
            if (!is_int($value)) {
                continue;
            }
            $flags[$name] = $value;
        }
        return $flags;
    }
}
