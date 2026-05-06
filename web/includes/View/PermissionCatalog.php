<?php
declare(strict_types=1);

namespace Sbpp\View;

/**
 * Display-side helper that groups web-permission flags into the
 * categories the panel UI uses to scan them — Bans / Servers / Admins
 * / Groups / Mods / Settings / Owner.
 *
 * Why this lives next to the View layer (not next to {@see Perms}):
 *
 *   - {@see Perms::for()} produces the **gate** snapshot: a flat
 *     `['can_<flag>' => bool, …]` map every page-View consumes to
 *     decide whether to render a button or a link. Categorisation
 *     would be noise in that surface — every gate already knows what
 *     it gates.
 *   - This catalog produces the **display** structure: a list of
 *     `{key, label, perms}` groups for the surfaces that print the
 *     user's granted permissions back to them (currently the
 *     "Your permissions" card on `page_youraccount.tpl`, addressed
 *     by #1207 ADM-9). Splitting "what flags do I hold" from "what
 *     buckets does the UI present them in" keeps `web.json` (the
 *     source of truth for flag values) decoupled from the UX
 *     categorisation, so a future copy-tweak that renames "Bans" to
 *     "Bans &amp; appeals" doesn't ripple through the canonical JSON
 *     or the SourceMod plugin's parallel parsing of web flags.
 *
 * Categorisation contract (every constant in
 * `web/configs/permissions/web.json` except the meta-bucket
 * `ALL_WEB` belongs to **exactly one** category — the
 * `PermissionCatalogTest` integration test pins this; adding a flag
 * to `web.json` without also adding it here breaks the test, on
 * purpose, so a new flag isn't silently invisible on the account
 * page).
 *
 * Owner bypass: the helper mirrors the convention baked into
 * `BitToString()` and every `CheckAdminAccess(ADMIN_OWNER|…)` site —
 * an admin holding `ADMIN_OWNER` lights up every category. The owner
 * row itself stays in the `owner` category so the rendering can call
 * out "yes, this admin has the keys" prominently.
 *
 * The `ADMIN_*` constants are defined globally by `init.php` from
 * `web/configs/permissions/web.json`; this helper resolves them via
 * `defined()`/`constant()` so a stripped-down test bootstrap that
 * skips init.php still degrades gracefully (an unresolved constant
 * is treated as not-granted instead of fatally erroring).
 */
final class PermissionCatalog
{
    /**
     * Ordered list of UI categories. Order is the render order on
     * the account page — bans first because most admins use the panel
     * to triage bans; owner last as the "you have the master key"
     * coda. The keys are stable test ids
     * (`account-perm-cat-<key>` in the template) so e2e specs can
     * anchor on them without depending on visible labels.
     *
     * The `constants` list names every `ADMIN_*` constant that should
     * appear in the category. Strings (not int values) on purpose:
     * `init.php` defines these constants at runtime from
     * `web/configs/permissions/web.json`, and `web.json` is the
     * source of truth for the int values — referencing constant
     * **names** here means a future bit-pack reshuffle in `web.json`
     * doesn't ripple through this file.
     *
     * @var list<array{key: string, label: string, constants: list<string>}>
     */
    public const WEB_CATEGORIES = [
        [
            'key'       => 'bans',
            'label'     => 'Bans',
            'constants' => [
                'ADMIN_ADD_BAN',
                'ADMIN_EDIT_OWN_BANS',
                'ADMIN_EDIT_GROUP_BANS',
                'ADMIN_EDIT_ALL_BANS',
                'ADMIN_BAN_PROTESTS',
                'ADMIN_BAN_SUBMISSIONS',
                'ADMIN_DELETE_BAN',
                'ADMIN_UNBAN',
                'ADMIN_UNBAN_OWN_BANS',
                'ADMIN_UNBAN_GROUP_BANS',
                'ADMIN_BAN_IMPORT',
                'ADMIN_NOTIFY_SUB',
                'ADMIN_NOTIFY_PROTEST',
            ],
        ],
        [
            'key'       => 'servers',
            'label'     => 'Servers',
            'constants' => [
                'ADMIN_LIST_SERVERS',
                'ADMIN_ADD_SERVER',
                'ADMIN_EDIT_SERVERS',
                'ADMIN_DELETE_SERVERS',
            ],
        ],
        [
            'key'       => 'admins',
            'label'     => 'Admins',
            'constants' => [
                'ADMIN_LIST_ADMINS',
                'ADMIN_ADD_ADMINS',
                'ADMIN_EDIT_ADMINS',
                'ADMIN_DELETE_ADMINS',
            ],
        ],
        [
            'key'       => 'groups',
            'label'     => 'Groups',
            'constants' => [
                'ADMIN_LIST_GROUPS',
                'ADMIN_ADD_GROUP',
                'ADMIN_EDIT_GROUPS',
                'ADMIN_DELETE_GROUPS',
            ],
        ],
        [
            'key'       => 'mods',
            'label'     => 'Mods',
            'constants' => [
                'ADMIN_LIST_MODS',
                'ADMIN_ADD_MODS',
                'ADMIN_EDIT_MODS',
                'ADMIN_DELETE_MODS',
            ],
        ],
        [
            'key'       => 'settings',
            'label'     => 'Settings',
            'constants' => [
                'ADMIN_WEB_SETTINGS',
            ],
        ],
        [
            'key'       => 'owner',
            'label'     => 'Owner',
            'constants' => [
                'ADMIN_OWNER',
            ],
        ],
    ];

    /**
     * Cache for the flat permission catalog read off
     * `web/configs/permissions/web.json`. The file has ~30 rows and
     * is read by `BitToString` + `init.php` already; caching keeps
     * the per-request total at one decode regardless of how many
     * View instances ask the catalog for their group list.
     *
     * @var array<string, array{value: int, display: string}>|null
     */
    private static ?array $flatCatalog = null;

    /**
     * Disallow instantiation — this is a static helper namespace.
     */
    private function __construct()
    {
    }

    /**
     * Expand a user's `extraflags` bitmask into the list of UI
     * categories with their granted display strings. Categories
     * without any granted permissions are filtered out so the
     * renderer doesn't paint empty buckets — a "Bans" heading with
     * zero rows underneath would read as "you have lost your bans
     * permissions" rather than "this category isn't relevant to
     * your role".
     *
     * Owner bypass: holding `ADMIN_OWNER` lights up every category
     * (mirrors `BitToString`'s behaviour).
     *
     * @return list<array{key: string, label: string, perms: list<string>}>
     *     Empty list when `$mask === 0` (no permissions; the template
     *     branch above renders an "(none)" empty state then).
     */
    public static function groupedDisplayFromMask(int $mask): array
    {
        if ($mask === 0) {
            return [];
        }

        $catalog = self::flatCatalog();
        $owner   = self::resolveConstant('ADMIN_OWNER');
        $allWeb  = self::resolveConstant('ALL_WEB');
        $hasOwner = $owner !== null && ($mask & $owner) !== 0;

        $out = [];
        foreach (self::WEB_CATEGORIES as $category) {
            $perms = [];
            foreach ($category['constants'] as $name) {
                $entry = $catalog[$name] ?? null;
                if ($entry === null) {
                    continue;
                }
                if ($allWeb !== null && $entry['value'] === $allWeb) {
                    continue;
                }
                $isGranted = ($mask & $entry['value']) !== 0 || $hasOwner;
                if (!$isGranted) {
                    continue;
                }
                $perms[] = $entry['display'];
            }
            if ($perms === []) {
                continue;
            }
            $out[] = [
                'key'   => $category['key'],
                'label' => $category['label'],
                'perms' => $perms,
            ];
        }

        return $out;
    }

    /**
     * Read `web/configs/permissions/web.json` once per process and
     * cache the keyed map. `web.json`'s shape is
     * `{ ADMIN_FOO: { value: int, display: string }, … }`; the cache
     * preserves that shape so callers can look up by constant name.
     *
     * @return array<string, array{value: int, display: string}>
     */
    private static function flatCatalog(): array
    {
        if (self::$flatCatalog !== null) {
            return self::$flatCatalog;
        }

        $path = self::webJsonPath();
        if ($path === null) {
            return self::$flatCatalog = [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return self::$flatCatalog = [];
        }
        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::$flatCatalog = [];
        }

        $out = [];
        foreach ($decoded as $name => $row) {
            if (!is_string($name) || !is_array($row)) {
                continue;
            }
            $value   = $row['value']   ?? null;
            $display = $row['display'] ?? null;
            if (!is_int($value) || !is_string($display)) {
                continue;
            }
            $out[$name] = ['value' => $value, 'display' => $display];
        }

        return self::$flatCatalog = $out;
    }

    /**
     * Resolve a top-level constant name to its int value, returning
     * `null` if it isn't currently defined. The helper exists so the
     * grouping logic can stay tolerant of test bootstraps that skip
     * `init.php` (the alternative — calling `constant()` unguarded —
     * would fatal there).
     */
    private static function resolveConstant(string $name): ?int
    {
        if (!defined($name)) {
            return null;
        }
        $value = constant($name);
        return is_int($value) ? $value : null;
    }

    /**
     * Locate `web/configs/permissions/web.json`. Prefers the runtime
     * `ROOT` constant set by `init.php` (so the runtime path
     * survives self-hoster customisation of the install layout), and
     * falls back to a path relative to this file for the test
     * bootstrap which doesn't always bring up the full bootstrap.
     */
    private static function webJsonPath(): ?string
    {
        if (defined('ROOT')) {
            $candidate = rtrim((string) constant('ROOT'), '/\\') . '/configs/permissions/web.json';
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        $candidate = __DIR__ . '/../../configs/permissions/web.json';
        if (is_file($candidate)) {
            return $candidate;
        }
        return null;
    }
}
