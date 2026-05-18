<?php

/**
 * @throws ErrorException
 */
function route(int|string $fallback): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        CSRF::rejectIfInvalid();
    }

    $page      = $_GET['p'] ?? null;
    $categorie = $_GET['c'] ?? null;
    $option    = $_GET['o'] ?? null;

    /*
     * Top-level dispatch. Most arms return a [$title, $template] tuple
     * directly; admin / fallback / steam-login / logout return a sentinel
     * the if-ladder below handles. Side-effect routes (Steam OpenID
     * round-trip, logout) can't live inside a `match` arm because the
     * arm has to yield a value before the side effect runs — `header()`
     * and `exit()` need their own explicit branch above the table. The
     * admin sentinel defers to the per-`c=…` route table further down.
     */
    $resolved = match ($page) {
        'login'        => $option === 'steam' ? '__steam_login__' : ['Login', '/page.login.php'],
        'logout'       => '__logout__',
        'submit'       => ['Submit a Ban',                '/page.submit.php'],
        'banlist'      => ['Ban List',                    '/page.banlist.php'],
        'commslist'    => ['Communications Block List',   '/page.commslist.php'],
        'servers'      => ['Server List',                 '/page.servers.php'],
        'protest'      => ['Protest a Ban',               '/page.protest.php'],
        'account'      => ['Your Account',                '/page.youraccount.php'],
        'lostpassword' => ['Lost your password',          '/page.lostpassword.php'],
        'home'         => ['Dashboard',                   '/page.home.php'],
        'admin'        => '__admin__',
        default        => '__fallback__',
    };

    if ($resolved === '__steam_login__') {
        require_once 'includes/Auth/openid.php';
        new SteamAuthHandler(new LightOpenID(Host::complete()), $GLOBALS['PDO']);
        exit();
    }
    if ($resolved === '__logout__') {
        Auth::logout();
        header('Location: index.php?p=home');
        exit();
    }

    if ($resolved === '__admin__') {
        /*
         * Admin sub-route table. Each top-level key is the `c=…` slug.
         * Every category gates on its own permission mask; the option
         * sub-table picks the leaf route and falls through to the
         * category default when `o=…` is missing or unrecognised.
         *
         * @var array<string, array{permission: int, options: array<string, array{0: string, 1: string}>, default: array{0: string, 1: string}}> $adminRoutes
         */
        $adminRoutes = [
            'groups' => [
                'permission' => ADMIN_OWNER|ADMIN_LIST_GROUPS|ADMIN_ADD_GROUP|ADMIN_EDIT_GROUPS|ADMIN_DELETE_GROUPS,
                'options'    => ['edit' => ['Edit Groups', '/admin.edit.group.php']],
                'default'    => ['Group Management', '/admin.groups.php'],
            ],
            'admins' => [
                'permission' => ADMIN_OWNER|ADMIN_LIST_ADMINS|ADMIN_ADD_ADMINS|ADMIN_EDIT_ADMINS|ADMIN_DELETE_ADMINS,
                'options'    => [
                    'editgroup'       => ['Edit Admin Groups',      '/admin.edit.admingroup.php'],
                    'editdetails'     => ['Edit Admin Details',     '/admin.edit.admindetails.php'],
                    'editpermissions' => ['Edit Admin Permissions', '/admin.edit.adminperms.php'],
                    'editservers'     => ['Edit Server Access',     '/admin.edit.adminservers.php'],
                ],
                'default' => ['Admin Management', '/admin.admins.php'],
            ],
            'servers' => [
                'permission' => ADMIN_OWNER|ADMIN_LIST_SERVERS|ADMIN_ADD_SERVER|ADMIN_EDIT_SERVERS|ADMIN_DELETE_SERVERS,
                'options'    => [
                    'edit'       => ['Edit Server',   '/admin.edit.server.php'],
                    'rcon'       => ['Server RCON',   '/admin.rcon.php'],
                    'admincheck' => ['Server Admins', '/admin.srvadmins.php'],
                ],
                'default' => ['Server Management', '/admin.servers.php'],
            ],
            'bans' => [
                'permission' => ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_ALL_BANS|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS,
                'options'    => [
                    'edit'  => ['Edit Ban Details', '/admin.edit.ban.php'],
                    'email' => ['Email',            '/admin.email.php'],
                ],
                'default' => ['Bans', '/admin.bans.php'],
            ],
            'comms' => [
                'permission' => ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_ALL_BANS,
                'options'    => ['edit' => ['Edit Block Details', '/admin.edit.comms.php']],
                'default'    => ['Comms', '/admin.comms.php'],
            ],
            'mods' => [
                'permission' => ADMIN_OWNER|ADMIN_LIST_MODS|ADMIN_ADD_MODS|ADMIN_EDIT_MODS|ADMIN_DELETE_MODS,
                'options'    => ['edit' => ['Edit Mod Details', '/admin.edit.mod.php']],
                'default'    => ['Manage Mods', '/admin.mods.php'],
            ],
            'settings' => [
                'permission' => ADMIN_OWNER|ADMIN_WEB_SETTINGS,
                'options'    => [],
                'default'    => ['SourceBans++ Settings', '/admin.settings.php'],
            ],
            'audit' => [
                // Audit log is owner-restricted: there is no dedicated
                // ADMIN_LIST_LOGS / ADMIN_LIST_AUDIT flag in
                // web/configs/permissions/web.json (#1123 B19), and the
                // legacy `TRUNCATE :prefix_log` path in
                // admin.settings.php is also gated on ADMIN_OWNER.
                'permission' => ADMIN_OWNER,
                'options'    => [],
                'default'    => ['Audit Log', '/admin.audit.php'],
            ],
        ];

        if (is_string($categorie) && isset($adminRoutes[$categorie])) {
            $admRoute = $adminRoutes[$categorie];
            CheckAdminAccess($admRoute['permission']);
            return $admRoute['options'][(string) $option] ?? $admRoute['default'];
        }

        // Unrecognised `c=…` used to silently fall through to the admin
        // home (#1207 ADM-1), which made typos and stale bookmarks
        // invisible (?p=admin&c=overrides looked indistinguishable
        // from ?p=admin). Surface them as a 404 instead. The naked
        // admin landing (`?p=admin` with no c=) still renders the home.
        if ($categorie !== null && $categorie !== '') {
            http_response_code(404);
            return ['Page not found', '/page.404.php'];
        }
        CheckAdminAccess(ALL_WEB);
        return ['Administration', '/page.admin.php'];
    }

    if ($resolved === '__fallback__') {
        // `?p=…` didn't match any top-level slug. The caller passes a
        // numeric `$fallback` mode so each entry sets `$_GET['p']` to
        // the canonical slug downstream code (page-tail navbar, etc.)
        // would have seen for a direct request.
        //
        // `$fallback` typically arrives from `Config::get('config.defaultpage')`,
        // which reads `sb_settings.value` (a `text NOT NULL` column) and so
        // returns a string like "1". `match` is strict (===), so we cast to
        // int at the dispatch boundary; non-numeric strings (`"banana"`) cast
        // to `0` and fall through to the Dashboard arm — matching the loose
        // `==` `switch` this replaced (#1290).
        [$slug, $title, $template] = match ((int) $fallback) {
            1       => ['banlist', 'Ban List',      '/page.banlist.php'],
            2       => ['servers', 'Server Info',   '/page.servers.php'],
            3       => ['submit',  'Submit a Ban',  '/page.submit.php'],
            4       => ['protest', 'Protest a Ban', '/page.protest.php'],
            default => ['home',    'Dashboard',     '/page.home.php'],
        };
        $_GET['p'] = $slug;
        return [$title, $template];
    }

    return $resolved;
}

function build(string $title, string $page): void
{
    require_once(TEMPLATES_PATH.'/core/header.php');
    require_once(TEMPLATES_PATH.'/core/navbar.php');
    require_once(TEMPLATES_PATH.'/core/title.php');
    require_once(TEMPLATES_PATH.$page);
    require_once(TEMPLATES_PATH.'/core/footer.php');
}
