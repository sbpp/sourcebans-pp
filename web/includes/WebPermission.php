<?php
/**
 * Backed enum wrapping the integer bitmask web permissions defined in
 * `web/configs/permissions/web.json` (and `define`d into the global
 * namespace as `ADMIN_*` constants by `init.php`).
 *
 * The on-disk representation (the `extraflags` / `flags` integer
 * column on `:prefix_admins` and `:prefix_groups` — `int(10)
 * UNSIGNED`) stays an `int`. This enum is a PHP-side type-safe
 * wrapper. At every SQL bind site, pass `$enum->value` (the int) or
 * `WebPermission::mask(...)` for a multi-flag bitmask — the case
 * itself is for in-PHP type safety only.
 *
 * `HasAccess()` is **not** variadic — its second arg is `$aid`. Use:
 *
 *   - Single-flag check:
 *     `HasAccess(WebPermission::Owner)`.
 *   - Multi-flag check (any-of, OR-mask):
 *     `HasAccess(WebPermission::mask(WebPermission::Owner,
 *                                     WebPermission::AddBan))`.
 *
 * The legacy `define`d `ADMIN_*` constants in `init.php` are
 * preserved for procedural-code back-compat: `HasAccess(ADMIN_OWNER
 * | ADMIN_ADD_BAN)` (legacy) and the `WebPermission::mask(...)` shape
 * (modern) both resolve to the same integer bitmask. Issue #1290
 * phase D.4.
 *
 * Cases are listed numerically by bit position (1, 2, 4, 8, 16, …)
 * so the bit-power progression is visible at a glance. The
 * `web.json` grouping (Bans / Servers / Admins / Groups / Mods /
 * Settings) doesn't reflect linearly here; consult
 * `Sbpp\View\PermissionCatalog` for the category-grouped display
 * layout.
 *
 * The pin between this enum and `web/configs/permissions/web.json`
 * is locked by
 * `Sbpp\Tests\Unit\WebPermissionTest::testWebPermissionEnumMatchesWebJson`
 * — the regression guard that fails the build if a flag is added /
 * renumbered without a matching case here (or vice versa).
 */
enum WebPermission: int
{
    case ListAdmins      = 1;
    case AddAdmins       = 2;
    case EditAdmins      = 4;
    case DeleteAdmins    = 8;
    case ListServers     = 16;
    case AddServer       = 32;
    case EditServers     = 64;
    case DeleteServers   = 128;
    case AddBan          = 256;
    case EditOwnBans     = 1024;
    case EditGroupBans   = 2048;
    case EditAllBans     = 4096;
    case BanProtests     = 8192;
    case BanSubmissions  = 16384;
    case ListGroups      = 32768;
    case AddGroup        = 65536;
    case EditGroups      = 131072;
    case DeleteGroups    = 262144;
    case WebSettings     = 524288;
    case ListMods        = 1048576;
    case AddMods         = 2097152;
    case EditMods        = 4194304;
    case DeleteMods      = 8388608;
    case Owner           = 16777216;
    case DeleteBan       = 33554432;
    case Unban           = 67108864;
    case BanImport       = 134217728;
    case NotifySub       = 268435456;
    case NotifyProtest   = 536870912;
    case UnbanOwnBans    = 1073741824;
    case UnbanGroupBans  = 2147483648;

    /**
     * Combine a list of permissions into a single bitmask.
     *
     * Replaces the legacy `ADMIN_OWNER | ADMIN_ADD_BAN` shape with
     * `WebPermission::mask(WebPermission::Owner, WebPermission::AddBan)`.
     *
     * @param WebPermission ...$flags
     */
    public static function mask(WebPermission ...$flags): int
    {
        $bits = 0;
        foreach ($flags as $flag) {
            $bits |= $flag->value;
        }
        return $bits;
    }

    /**
     * Decode an integer bitmask into the list of `WebPermission`
     * cases it carries. Useful for "which permissions does this
     * group have?" introspection.
     *
     * @param int $mask
     * @return list<WebPermission>
     */
    public static function fromMask(int $mask): array
    {
        $found = [];
        foreach (self::cases() as $case) {
            if (($mask & $case->value) === $case->value) {
                $found[] = $case;
            }
        }
        return $found;
    }
}
