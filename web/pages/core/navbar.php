<?php
global $userbank, $theme;

$navbar = [
     [
        'title' => 'Dashboard',
        'endpoint' => 'home',
        'description' => 'This page shows an overview of your bans and servers.',
        'permission' => true
    ],
    [
        'title' => 'Servers',
        'endpoint' => 'servers',
        'description' => 'All of your servers and their status can be viewed here.',
        'permission' => true
    ],
    [
        'title' => 'Bans',
        'endpoint' => 'banlist',
        'description' => 'All of the bans in the database can be viewed from here.',
        'permission' => true
    ],
    [
        'title' => 'Comms',
        'endpoint' => 'commslist',
        'description' => 'All of the communication bans (such as chat gags and voice mutes) in the database can be viewed from here.',
        'permission' => Config::getBool('config.enablecomms')
    ],
    [
        'title' => 'Report a Player',
        'endpoint' => 'submit',
        'description' => 'You can submit a demo or screenshot of a suspected cheater here. It will then be up for review by one of the admins.',
        'permission' => Config::getBool('config.enablesubmit')
    ],
    [
        'title' => 'Appeal a Ban',
        'endpoint' => 'protest',
        'description' => 'Here you can appeal your ban. And prove your case as to why you should be unbanned.',
        'permission' => Config::getBool('config.enableprotest')
    ],
    [
        'title' => 'Admin Panel',
        'endpoint' => 'admin',
        'description' => 'This is the control panel for SourceBans where you can setup new admins, add new server, etc.',
        'permission' => $userbank->is_admin()
    ]
];

$admin = [
    [
        'title' => 'Admins',
        'endpoint' => 'admins',
        'permission' => ADMIN_OWNER|ADMIN_LIST_ADMINS|ADMIN_ADD_ADMINS|ADMIN_EDIT_ADMINS|ADMIN_DELETE_ADMINS
    ],
    [
        'title' => 'Servers',
        'endpoint' => 'servers',
        'permission' => ADMIN_OWNER|ADMIN_LIST_SERVERS|ADMIN_ADD_SERVER|ADMIN_EDIT_SERVERS|ADMIN_DELETE_SERVERS
    ],
    [
        'title' => 'Bans',
        'endpoint' => 'bans',
        'permission' => ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_ALL_BANS|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS
    ],
    [
        'title' => 'Comms',
        'endpoint' => 'comms',
        'permission' => ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_ALL_BANS
    ],
    [
        'title' => 'Groups',
        'endpoint' => 'groups',
        'permission' => ADMIN_OWNER|ADMIN_LIST_GROUPS|ADMIN_ADD_GROUP|ADMIN_EDIT_GROUPS|ADMIN_DELETE_GROUPS
    ],
    [
        'title' => 'Settings',
        'endpoint' => 'settings',
        'permission' => ADMIN_OWNER|ADMIN_WEB_SETTINGS
    ],
    [
        'title' => 'Mods',
        'endpoint' => 'mods',
        'permission' => ADMIN_OWNER|ADMIN_LIST_MODS|ADMIN_ADD_MODS|ADMIN_EDIT_MODS|ADMIN_DELETE_MODS
    ]
];

$active = filter_input(INPUT_GET, 'p', FILTER_SANITIZE_STRING);
foreach ($navbar as $key => $tab) {
    $navbar[$key]['state'] = ($active === $tab['endpoint']) ? 'active' : 'nonactive';

    if (!$tab['permission']) {
        unset($navbar[$key]);
    }
}

if ($userbank->is_admin()) {
    $cat = filter_input(INPUT_GET, 'c', FILTER_SANITIZE_STRING);
    foreach ($admin as $key => $tab) {
        $admin[$key]['state'] = ($cat === $tab['endpoint']) ? 'active' : '';

        if (!$userbank->HasAccess($tab['permission'])) {
            unset($admin[$key]);
        }
    }
}

$theme->assign('navbar', array_values($navbar));
$theme->assign('adminbar', array_values($admin));
$theme->assign('isAdmin', $userbank->is_admin());
$theme->assign('login', $userbank->is_logged_in());
$theme->assign('username', $userbank->GetProperty("user"));
$theme->display('core/navbar.tpl');
