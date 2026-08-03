<?php
declare(strict_types=1);

namespace Sbpp\View;

use Sbpp\Auth\UserManager;
use Sbpp\Config;

/**
 * Section catalogs for Pattern A admin routes (`?section=<slug>`).
 *
 * Single source for the nested children the main sidebar accordion
 * renders (#1490) and for the `$sections` arrays page handlers use
 * for routing defaults. Permission and feature-toggle gates match
 * the pre-#1490 AdminTabs filter so a link either appears in the
 * accordion and resolves, or is absent from both.
 *
 * @phpstan-type TabSpec array{
 *     name: string,
 *     permission: int|string,
 *     url: string,
 *     slug: string,
 *     icon?: string,
 *     config?: bool,
 * }
 */
final class AdminNavCatalog
{
    private function __construct()
    {
    }

    /**
     * Unfiltered section catalog for one admin endpoint (`c=` value).
     * Empty list means the category has no accordion children (comms,
     * export) or is unknown.
     *
     * @return list<TabSpec>
     */
    public static function sectionsFor(string $endpoint): array
    {
        return match ($endpoint) {
            'admins' => [
                [
                    'slug'       => 'admins',
                    'name'       => 'Admins',
                    'permission' => ADMIN_OWNER | ADMIN_LIST_ADMINS,
                    'url'        => 'index.php?p=admin&c=admins&section=admins',
                    'icon'       => 'users',
                ],
                [
                    'slug'       => 'add-admin',
                    'name'       => 'Add admin',
                    'permission' => ADMIN_OWNER | ADMIN_ADD_ADMINS,
                    'url'        => 'index.php?p=admin&c=admins&section=add-admin',
                    'icon'       => 'user-plus',
                ],
                [
                    'slug'       => 'overrides',
                    'name'       => 'Overrides',
                    'permission' => ADMIN_OWNER | ADMIN_ADD_ADMINS,
                    'url'        => 'index.php?p=admin&c=admins&section=overrides',
                    'icon'       => 'shield',
                ],
            ],
            'servers' => [
                [
                    'slug'       => 'list',
                    'name'       => 'List servers',
                    'permission' => ADMIN_OWNER | ADMIN_LIST_SERVERS,
                    'url'        => 'index.php?p=admin&c=servers&section=list',
                    'icon'       => 'server',
                ],
                [
                    'slug'       => 'add',
                    'name'       => 'Add new server',
                    'permission' => ADMIN_OWNER | ADMIN_ADD_SERVER,
                    'url'        => 'index.php?p=admin&c=servers&section=add',
                    'icon'       => 'plus',
                ],
            ],
            'bans' => self::bansSections(),
            'groups' => [
                [
                    'slug'       => 'list',
                    'name'       => 'List groups',
                    'permission' => ADMIN_OWNER | ADMIN_LIST_GROUPS,
                    'url'        => 'index.php?p=admin&c=groups&section=list',
                    'icon'       => 'users',
                ],
                [
                    'slug'       => 'add',
                    'name'       => 'Add a group',
                    'permission' => ADMIN_OWNER | ADMIN_ADD_GROUP,
                    'url'        => 'index.php?p=admin&c=groups&section=add',
                    'icon'       => 'plus',
                ],
            ],
            'settings' => [
                [
                    'slug'       => 'settings',
                    'name'       => 'Main',
                    'permission' => ADMIN_OWNER | ADMIN_WEB_SETTINGS,
                    'url'        => 'index.php?p=admin&c=settings&section=settings',
                    'icon'       => 'settings',
                ],
                [
                    'slug'       => 'features',
                    'name'       => 'Features',
                    'permission' => ADMIN_OWNER | ADMIN_WEB_SETTINGS,
                    'url'        => 'index.php?p=admin&c=settings&section=features',
                    'icon'       => 'toggle-right',
                ],
                [
                    'slug'       => 'logs',
                    'name'       => 'System Log',
                    'permission' => ADMIN_OWNER | ADMIN_WEB_SETTINGS,
                    'url'        => 'index.php?p=admin&c=settings&section=logs',
                    'icon'       => 'scroll-text',
                ],
                [
                    'slug'       => 'themes',
                    'name'       => 'Themes',
                    'permission' => ADMIN_OWNER | ADMIN_WEB_SETTINGS,
                    'url'        => 'index.php?p=admin&c=settings&section=themes',
                    'icon'       => 'palette',
                ],
            ],
            'mods' => [
                [
                    'slug'       => 'list',
                    'name'       => 'List MODs',
                    'permission' => ADMIN_OWNER | ADMIN_LIST_MODS,
                    'url'        => 'index.php?p=admin&c=mods&section=list',
                    'icon'       => 'puzzle',
                ],
                [
                    'slug'       => 'add',
                    'name'       => 'Add new MOD',
                    'permission' => ADMIN_OWNER | ADMIN_ADD_MODS,
                    'url'        => 'index.php?p=admin&c=mods&section=add',
                    'icon'       => 'plus',
                ],
            ],
            default => [],
        };
    }

    /**
     * Filter a section list the same way AdminTabs did: drop entries
     * the user cannot reach and honour optional `config` toggles.
     *
     * @param list<TabSpec> $sections
     * @return list<TabSpec>
     */
    public static function filterForUser(array $sections, UserManager $userbank): array
    {
        $out = [];
        foreach ($sections as $tab) {
            if (!$userbank->HasAccess($tab['permission'])) {
                continue;
            }
            if (isset($tab['config']) && !$tab['config']) {
                continue;
            }
            $out[] = $tab;
        }
        return $out;
    }

    /**
     * @return list<TabSpec>
     */
    private static function bansSections(): array
    {
        $sections = [
            [
                'slug'       => 'add-ban',
                'name'       => 'Add a ban',
                'permission' => ADMIN_OWNER | ADMIN_ADD_BAN,
                'url'        => 'index.php?p=admin&c=bans&section=add-ban',
                'icon'       => 'plus',
            ],
        ];

        if (Config::getBool('config.enableprotest')) {
            $sections[] = [
                'slug'       => 'protests',
                'name'       => 'Ban protests',
                'permission' => ADMIN_OWNER | ADMIN_BAN_PROTESTS,
                'url'        => 'index.php?p=admin&c=bans&section=protests',
                'icon'       => 'flag',
            ];
        }
        if (Config::getBool('config.enablesubmit')) {
            $sections[] = [
                'slug'       => 'submissions',
                'name'       => 'Ban submissions',
                'permission' => ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS,
                'url'        => 'index.php?p=admin&c=bans&section=submissions',
                'icon'       => 'clipboard-list',
            ];
        }

        $sections[] = [
            'slug'       => 'import',
            'name'       => 'Import bans',
            'permission' => ADMIN_OWNER | ADMIN_BAN_IMPORT,
            'url'        => 'index.php?p=admin&c=bans&section=import',
            'icon'       => 'upload',
        ];

        if (Config::getBool('config.enablegroupbanning')) {
            $sections[] = [
                'slug'       => 'group-ban',
                'name'       => 'Group ban',
                'permission' => ADMIN_OWNER | ADMIN_ADD_BAN,
                'url'        => 'index.php?p=admin&c=bans&section=group-ban',
                'icon'       => 'users',
            ];
        }

        return $sections;
    }
}
