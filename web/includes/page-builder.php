<?php

/**
 * @param $fallback
 * @return array
 * @throws ErrorException
 */
function route($fallback)
{
    $page = filter_input(INPUT_GET, 'p', FILTER_SANITIZE_STRING);
    $categorie = filter_input(INPUT_GET, 'c', FILTER_SANITIZE_STRING);
    $option = filter_input(INPUT_GET, 'o', FILTER_SANITIZE_STRING);

    switch ($page) {
        case 'login':
            switch ($option) {
                case 'steam':
                    require_once 'includes/auth/openid.php';
                    new SteamAuthHandler(new LightOpenID(Host::complete()), $GLOBALS['PDO']);
                    exit();
                default:
                    return ['Login', '/page.login.php'];
            }
        case 'logout':
            Auth::logout();
            header('Location: index.php?p=home');
            exit();
        case 'submit':
            return ['Submit a Ban', '/page.submit.php'];
        case 'banlist':
            return ['Ban List', '/page.banlist.php'];
        case 'commslist':
            return ['Communications Block List', '/page.commslist.php'];
        case 'servers':
            return ['Server List', '/page.servers.php'];
        case 'protest':
            return ['Protest a Ban', '/page.protest.php'];
        case 'account':
            return ['Your Account', '/page.youraccount.php'];
        case 'lostpassword':
            return ['Lost your password', '/page.lostpassword.php'];
        case 'home':
            return ['Dashboard', '/page.home.php'];
        case 'admin':
            switch ($categorie) {
                case 'groups':
                    CheckAdminAccess(ADMIN_OWNER|ADMIN_LIST_GROUPS|ADMIN_ADD_GROUP|ADMIN_EDIT_GROUPS|ADMIN_DELETE_GROUPS);
                    switch ($option) {
                        case 'edit':
                            return ['Edit Groups', '/admin.edit.group.php'];
                        default:
                            return ['Group Management', '/admin.groups.php'];
                    }
                case 'admins':
                    CheckAdminAccess(ADMIN_OWNER|ADMIN_LIST_ADMINS|ADMIN_ADD_ADMINS|ADMIN_EDIT_ADMINS|ADMIN_DELETE_ADMINS);
                    switch ($option) {
                        case 'editgroup':
                            return ['Edit Admin Groups', '/admin.edit.admingroup.php'];
                        case 'editdetails':
                            return ['Edit Admin Details', '/admin.edit.admindetails.php'];
                        case 'editpermissions':
                            return ['Edit Admin Permissions', '/admin.edit.adminperms.php'];
                        case 'editservers':
                            return ['Edit Server Access', '/admin.edit.adminservers.php'];
                        default:
                            return ['Admin Management', '/admin.admins.php'];
                    }
                case 'servers':
                    CheckAdminAccess(ADMIN_OWNER|ADMIN_LIST_SERVERS|ADMIN_ADD_SERVER|ADMIN_EDIT_SERVERS|ADMIN_DELETE_SERVERS);
                    switch ($option) {
                        case 'edit':
                            return ['Edit Server', '/admin.edit.server.php'];
                        case 'rcon':
                            return ['Server RCON', '/admin.rcon.php'];
                        case 'admincheck':
                            return ['Server Admins', '/admin.srvadmins.php'];
                        default:
                            return ['Server Management', '/admin.servers.php'];
                    }
                case 'bans':
                    CheckAdminAccess(ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_ALL_BANS|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS);
                    switch ($option) {
                        case 'edit':
                            return ['Edit Ban Details', '/admin.edit.ban.php'];
                        case 'email':
                            return ['Email', '/admin.email.php'];
                        default:
                            return ['Bans', '/admin.bans.php'];
                    }
                case 'comms':
                    CheckAdminAccess(ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_ALL_BANS);
                    switch ($option) {
                        case 'edit':
                            return ['Edit Block Details', '/admin.edit.comms.php'];
                        default:
                            return ['Comms', '/admin.comms.php'];
                    }
                case 'mods':
                    CheckAdminAccess(ADMIN_OWNER|ADMIN_LIST_MODS|ADMIN_ADD_MODS|ADMIN_EDIT_MODS|ADMIN_DELETE_MODS);
                    switch ($option) {
                        case 'edit':
                            return ['Edit Mod Details', '/admin.edit.mod.php'];
                        default:
                            return ['Manage Mods', '/admin.mods.php'];
                    }
                case 'settings':
                    CheckAdminAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS);
                    return ['SourceBans++ Settings', '/admin.settings.php'];
                default:
                    CheckAdminAccess(ALL_WEB);
                    return ['Administration', '/page.admin.php'];
        }
        default:
            switch ($fallback) {
                case 1:
                    $_GET['p'] = 'banlist';
                    return ['Ban List', '/page.banlist.php'];
                case 2:
                    $_GET['p'] = 'servers';
                    return ['Server Info', '/page.servers.php'];
                case 3:
                    $_GET['p'] = 'submit';
                    return ['Submit a Ban', '/page.submit.php'];
                case 4:
                    $_GET['p'] = 'protest';
                    return ['Protest a Ban', '/page.protest.php'];
                default:
                    $_GET['p'] = 'home';
                    return ['Dashboard', '/page.home.php'];
            }
    }
}

/**
 * @param null $title Unused
 * @param string $page
 */
function build($title, $page)
{
    require_once(TEMPLATES_PATH.'/core/header.php');
    require_once(TEMPLATES_PATH.'/core/navbar.php');
    require_once(TEMPLATES_PATH.'/core/title.php');
    require_once(TEMPLATES_PATH.$page);
    require_once(TEMPLATES_PATH.'/core/footer.php');
}
