<?php
/**
 * user-functions.php
 * 
 * This file contains all of the functions required
 * to manage the users of this system.
 * @author GameConnect Development Team
 * @version 0.0.0.3
 * @copyright GameConnect (www.gameconnect.info)
 * @package SourceBans
 */
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
    $query = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_$table` WHERE `$field` = '$value'");
    return (count($query) > 0);
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
    $query = $GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `user` = '$username',  `authid` = '$authid', `email` = '$email' WHERE `aid` = '$aid'");
    if($query)
    {
        return true;
    }
    else
    {
        return false;
    }
}

/**
 * Removes an admin from the system
 *
 * @param integer $aid The admin id of the admin to delete
 * @return true on success.
 */
function delete_admin($aid)
{
    $query = $GLOBALS['db']->Execute("DELETE FROM `" . DB_PREFIX . "_admins` WHERE `aid` = '$aid'");
    if($query)
    {
        return true;
    }
    else
    {
        return false;
    }
}

/**
 * Returns the current flags associated with the user
 *
 * @param integer The admin id to check
 * @return integer.
 */
function get_user_flags($aid)
{
	if(empty($aid))
		return 0;
	
	$admin = $query = $GLOBALS['db']->GetRow("SELECT `gid`, `extraflags` FROM `" . DB_PREFIX . "_admins` WHERE aid = '$aid'");
	if(intval($admin['gid']) == -1)
	{
		return intval($admin['extraflags']);
	}
	else 
	{
		$query = $GLOBALS['db']->GetRow("SELECT `flags` FROM `" . DB_PREFIX . "_groups` WHERE gid = (SELECT gid FROM " . DB_PREFIX . "_admins WHERE aid = '$aid')");
		return (intval($query['flags']) | intval($admin['extraflags']));
	}
	
}

/**
 * Returns the current server flags associated with the user
 *
 * @param string The admin to check
 * @return string.
 */
function get_user_admin($steam)
{	
	if(empty($steam))
		return 0;
	$admin = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_srvadmins` WHERE identity = '$steam'");
	if(strlen($admin['groups']) > 1)
	{
		$query = $GLOBALS['db']->GetRow("SELECT `flags` FROM `" . DB_PREFIX . "_srvgroups` WHERE name = (SELECT `groups` FROM " . DB_PREFIX . "_srvadmins WHERE identity = '$steam')");
		return $query['flags'] . $admin['flags'];
	}
	else 
	{
		return $admin['flags'];
	}
	
}

/**
 * Returns the current server flags associated with the user
 *
 * @param string The admin to check
 * @return string.
 */
function get_non_inherited_admin($steam)
{	
	if(empty($steam))
		return 0;
	$admin = $GLOBALS['db']->GetRow("SELECT * FROM `" . DB_PREFIX . "_srvadmins` WHERE identity = '$steam'");
	return $admin['flags'];	
}

/**
 * Checks if user is logged in.
 *
 * @return boolean.
 */
function is_logged_in()
{
	if($_SESSION['user']['user'] == "Guest" || $_SESSION['user']['user'] == "")
		return false;
	else 
		return true;
}

/**
 * Checks if user is an admin.
 *
 * @return boolean.
 */
function is_admin($aid)
{
	if (check_flags($aid, ALL_WEB))
		return true;
	else 
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
	SM_CHEATS|SM_ROOT|SM_DEF_IMMUNITY|SM_GLOBAL_IMMUNITY == 0))
		return GROUP_WEB_A;
	else if($mask == 0)
		return GROUP_NONE_A;
	else
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
	if(($mask & $flag) !=0)
		return true;
	else 
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
	if(($mask & $flag) !=0)
		return true;
	else 
		return false;
}

function validate_steam($steam)
{
	if(preg_match(STEAM_FORMAT, $steam))
		return true;
	else 
		return false;
}

function validate_email($email)
{
	if(preg_match(EMAIL_FORMAT, $email))
	{
		return true;
	}
	else 
	{
		return false; 
	}
}
?>