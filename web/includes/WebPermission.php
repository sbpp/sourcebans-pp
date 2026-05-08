<?php
/**
 * Backed enum wrapping the integer bitmask web permissions defined in
 * `web/configs/permissions/web.json` (and `define`d into the global
 * namespace as `ADMIN_*` constants by `init.php`).
 *
 * The on-disk representation (the `web_flags` integer column on
 * `:prefix_admins` and `:prefix_groups`) stays an `int`. This enum is
 * a PHP-side type-safe wrapper. At every SQL bind site, pass
 * `$enum->value` (the int) — the case itself is for in-PHP type
 * safety only.
 *
 * The legacy `define`d `ADMIN_*` constants in `init.php` are
 * preserved for procedural-code back-compat: `HasAccess(ADMIN_OWNER
 * | ADMIN_ADD_BAN)` (legacy) and `HasAccess(WebPermission::Owner,
 * WebPermission::AddBan)` (modern variadic) both resolve to the same
 * integer bitmask. Issue #1290 phase D.4.
 *
 * Cases are ordered to match `web/configs/permissions/web.json` so a
 * future test (or a human reader) can diff the two by eye; the int
 * value is the load-bearing contract, the case order isn't.
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
