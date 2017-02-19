<?php
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

global $user, $db;
/**
 * Checks the database for any identical
 * rows, username, email etc
 *
 * @param string $table the table to lookup
 * @param string $field The feild to check
 * @param string $value The value to check against
 * @return true if the value already exists in that field is found, else false
 */
function is_taken($table, $field, $value)
{
    $GLOBALS['db']->query('SELECT * FROM `:prefix_:table` WHERE `:field` = `:value`');
    $GLOBALS['db']->bind(':table', $table);
    $GLOBALS['db']->bind(':field', $field);
    $GLOBALS['db']->bind(':value', $value);
    $result = $GLOBALS['db']->resultset();
    return (count($result) > 0);
}


/**
 * Changes the admins data
 *
 * @param integer $aid The admin id to change the details of
 * @param string $username The new username of the admin
 * @param string $name The new realname of the admin
 * @param string $email The email of the admin
 * @param string $authid the STEAM of the admin
 * @return true on success.
 */
function edit_admin($aid, $username, $name, $email, $authid)
{
    $GLOBALS['db']->query('UPDATE `:prefix_admins` SET `user` = `:user`, `authid` = `:authid`, `email` = `:email` WHERE `aid` = `:aid`');
    $GLOBALS['db']->bind(':user', $username);
    $GLOBALS['db']->bind(':authid', $authid);
    $GLOBALS['db']->bind(':email', $email);
    $GLOBALS['db']->bind(':aid', $aid);
    return $GLOBALS['db']->execute();
}

/**
 * Removes an admin from the system
 *
 * @param integer $aid The admin id of the admin to delete
 * @return true on success.
 */
function delete_admin($aid)
{
    $GLOBALS['db']->query('DELETE FROM `:prefix_admins` WHERE `aid` = `:aid`');
    $GLOBALS['db']->bind(':aid', $aid);
    return $GLOBALS['db']->execute();
}

/**
 * Returns the current flags associated with the user
 *
 * @param integer The admin id to check
 * @return integer.
 */
function get_user_flags($aid)
{
    if (empty($aid)) {
        return 0;
    }

    $GLOBALS['db']->query('SELECT `gid`, `extraflags` FROM `:prefix_admins` WHERE `aid` = `:aid`');
    $GLOBALS['db']->bind(':aid', $aid);
    $admin = $GLOBALS['db']->single();

    if (intval($admin['gid']) === -1) {
        return intval($admin['extraflags']);
    }

    $GLOBALS['db']->query('SELECT `flags` FROM `:prefix_groups` WHERE `gid` = `:gid`');
    $GLOBALS['db']->bind(':gid', $admin['gid']);
    $group = $GLOBALS['db']->single();
    return (intval($group['flags']) | intval($admin['extraflags']));
}

/**
 * Returns the current server flags associated with the user
 *
 * @param string The admin to check
 * @return string.
 */
function get_user_admin($steam)
{
    if (empty($steam)) {
        return 0;
    }
    $GLOBALS['db']->query('SELECT * FROM `:prefix_srvadmins` WHERE `identity` = `:identity`');
    $GLOBALS['db']->bind(':identity', $steam);
    $admin = $GLOBALS['db']->single();

    if (strlen($admin['groups']) > 1) {
        $GLOBALS['db']->query('SELECT `flags` FROM `:prefix_srvgroups` WHERE `name` = `:name`');
        $GLOBALS['db']->bind(':name', $admin['groups']);
        $query = $GLOBALS['db']->single();
        return $query['flags'] . $admin['flags'];
    }
    return $admin['flags'];
}

/**
 * Returns the current server flags associated with the user
 *
 * @param string The admin to check
 * @return string.
 */
function get_non_inherited_admin($steam)
{
    if (empty($steam)) {
        return 0;
    }
    $GLOBALS['db']->query('SELECT * FROM `:prefix_srvadmins` WHERE `identity` = `:identity`');
    $GLOBALS['db']->bind(':identity', $steam);
    $admin = $GLOBALS['db']->single();
    return $admin['flags'];
}

/**
 * Checks if user is logged in.
 *
 * @return boolean.
 */
function is_logged_in()
{
    if ($_SESSION['user']['user'] == "Guest" || $_SESSION['user']['user'] == "") {
        return false;
    }
    return true;
}

/**
 * Checks if user is an admin.
 *
 * @return boolean.
 */
function is_admin($aid)
{
    if (check_flags($aid, ALL_WEB)) {
        return true;
    }
    return false;
}

/**
 * Checks which admin type the admin is
 * using the given mask
 *
 * @return integer.
 */
function check_group($mask)
{
    if ($mask &
    (ADMIN_WEB_BANS|ADMIN_WEB_ADMINS|ADMIN_WEB_AGROUPS|
    ADMIN_SERVER_ADMINS|ADMIN_SERVER_AGROUPS|ADMIN_SERVER_SETTINGS|
    ADMIN_SERVER_ADD|ADMIN_SERVER_REMOVE|ADMIN_SERVER_GROUPS|ADMIN_WEB_SETTINGS|
    ADMIN_OWNER|ADMIN_MODS != 0 && $mask &
    SM_RESERVED_SLOT|SM_GENERIC|SM_KICK|SM_BAN|SM_UNBAN|SM_SLAY|
    SM_MAP|SM_CVAR|SM_CONFIG|SM_CHAT|SM_VOTE|SM_PASSWORD|SM_RCON|
    SM_CHEATS|SM_ROOT|SM_DEF_IMMUNITY|SM_GLOBAL_IMMUNITY == 0)) {
        return GROUP_WEB_A;
    } elseif ($mask == 0) {
        return GROUP_NONE_A;
    }
    return GROUP_SERVER_A;
}



/**
 * Checks if the admin has ALL the specified flags
 *
 * @param integet $aid the admin id to check the flags of
 * @param integer $flag the flag to check
 * @return boolean
 */
function check_all_flags($aid, $flag)
{
    $mask = get_user_flags($aid);
    return ($mask & $flag) == $flag;
}

/**
 * Checks if the admin has ANY the specified flags
 *
 * @param integet $aid the admin id to check the flags of
 * @param integer $flag the flag to check
 * @return boolean
 */
function check_flags($aid, $flag)
{
    $mask = get_user_flags($aid);
    if (($mask & $flag) !=0) {
        return true;
    }
    return false;
}

/**
 * Checks if the mask contains ANY the specified flags
 *
 * @param integet $aid the admin id to check the flags of
 * @param integer $flag the flag to check
 * @return boolean
 */
function check_flag($mask, $flag)
{
    if (($mask & $flag) !=0) {
        return true;
    }
    return false;
}

function validate_steam($steam)
{
    if (preg_match(STEAM_FORMAT, $steam)) {
        return true;
    }
    return false;
}

function validate_email($email)
{
    if (preg_match(EMAIL_FORMAT, $email)) {
        return true;
    }
    return false;
}
