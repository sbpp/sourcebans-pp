<?php
/**
 * =============================================================================
 * Main user handler
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: CUserManager.php 182 2008-12-18 19:12:19Z smithxxl $
 * =============================================================================
 */

class CUserManager
{
	var $aid = -1;
	var $admins = array();
	
	/**
	 * Class constructor
	 *
	 * @param $aid the current user's aid
	 * @param $password the current user's password
	 * @return noreturn.
	 */
	function CUserManager($aid, $password)
	{
		if($this->CheckLogin($password, $aid))
		{
			$this->aid = $aid;
			$this->GetUserArray($aid);
		}
		else 
			$this->aid = -1;
	}
	
	
	/**
	 * Gets all user details from the database, saves them into
	 * the admin array 'cache', and then returns the array
	 *
	 * @param $aid the ID of admin to get info for.
	 * @return array.
	 */
	function GetUserArray($aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;	
		// Invalid aid
		if($aid < 0 || empty($aid))
			return 0;
		
		$aid = (int)$aid;
		// We already got the data from the DB, and its saved in the manager
		if(isset($this->admins[$aid]) && !empty($this->admins[$aid]))
			return $this->admins[$aid];
		// Not in the manager, so we need to get them from DB
		$res = $GLOBALS['db']->GetRow("SELECT adm.user user, adm.authid authid, adm.password password, adm.gid gid, adm.email email, adm.validate validate, adm.extraflags extraflags, 
									   adm.immunity admimmunity,sg.immunity sgimmunity, adm.srv_password srv_password, adm.srv_group srv_group, adm.srv_flags srv_flags,sg.flags sgflags,
									   wg.flags wgflags, wg.name wgname, adm.lastvisit lastvisit
									   FROM " . DB_PREFIX . "_admins AS adm
									   LEFT JOIN " . DB_PREFIX . "_groups AS wg ON adm.gid = wg.gid
									   LEFT JOIN " . DB_PREFIX . "_srvgroups AS sg ON adm.srv_group = sg.name
									   WHERE adm.aid = $aid");
		
		if(!$res)	
			return 0;  // ohnoes some type of db error
		
		$user = array();	
		//$user['user'] = stripslashes($res[0]);
		$user['aid'] = $aid; //immediately obvious
		$user['user'] = $res['user'];
		$user['authid'] = $res['authid'];	
		$user['password'] = $res['password'];
		$user['gid'] = $res['gid'];
		$user['email'] = $res['email'];
		$user['validate'] = $res['validate'];
		$user['extraflags'] = (intval($res['extraflags']) | intval($res['wgflags']));

		if(intval($res['admimmunity']) > intval($res['sgimmunity']))
			$user['srv_immunity'] = intval($res['admimmunity']);
		else 
			$user['srv_immunity'] = intval($res['sgimmunity']);

		$user['srv_password'] = $res['srv_password'];
		$user['srv_groups'] = $res['srv_group'];
		$user['srv_flags'] = $res['srv_flags'] . $res['sgflags'];
		$user['group_name'] = $res['wgname'];
		$user['lastvisit'] = $res['lastvisit'];
		$this->admins[$aid] = $user;
		return $user;
	}

	
	/**
	 * Will check to see if an admin has any of the flags given
	 *
	 * @param $flags The flags to check for.
	 * @param $aid The user to check flags for.
	 * @return boolean.
	 */
	function HasAccess($flags, $aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;
			
		if(empty($flags) || $aid <= 0)
			return false;
		
		$aid = (int)$aid;
		if(is_numeric($flags))
		{
			if(!isset($this->admins[$aid]))
				$this->GetUserArray($aid);
			return ($this->admins[$aid]['extraflags'] & $flags) != 0 ? true : false;
		}
		else 
		{
			if(!isset($this->admins[$aid]))
				$this->GetUserArray($aid);
			for($i=0;$i<strlen($this->admins[$aid]['srv_flags']);$i++)
			{
				for($a=0;$a<strlen($flags);$a++)
				{
					if(strstr($this->admins[$aid]['srv_flags'][$i], $flags[$a]))
						return true;
				}
			}
		}
	}
	
	
	/**
	 * Gets a 'property' from the user array eg. 'authid'
	 *
	 * @param $aid the ID of admin to get info for.
	 * @return mixed.
	 */
	function GetProperty($name, $aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;
		if(empty($name) || $aid < 0)
			return false;
		$aid = (int)$aid;	
		if(!isset($this->admins[$aid]))
			$this->GetUserArray($aid);
		
		return $this->admins[$aid][$name];
	}
	

	/**
	 * Will test the user's login stuff to check if they havnt changed their 
	 * cookies or something along those lines.
	 *
	 * @param $password The admins password.
	 * @param $aid the admins aid
	 * @return boolean.
	 */
	function CheckLogin($password, $aid)
	{
		$aid = (int)$aid;

		if(empty($password))
			return false;
		if(!isset($this->admins[$aid]))
			$this->GetUserArray($aid);
			
		if($password == $this->admins[$aid]['password'])
		{
			$GLOBALS['db']->Execute("UPDATE `" . DB_PREFIX . "_admins` SET `lastvisit` = UNIX_TIMESTAMP() WHERE `aid` = '$aid'");
			return true;
		}
		else 
			return false;
	}
	
	
	function login($aid, $password, $save = true)
	{
	    if($this->CheckLogin($this->encrypt_password($password), $aid))
	    {
	        if($save)
	        {
	            //Sets cookies
	            setcookie("aid", $aid, time()+LOGIN_COOKIE_LIFETIME);
	            setcookie("password", $this->encrypt_password($password), time()+LOGIN_COOKIE_LIFETIME);
	            setcookie("user", isset($_SESSION['user']['user'])?$_SESSION['user']['user']:null, time()+LOGIN_COOKIE_LIFETIME);
	        }
	        else 
	        {
	        	setcookie("aid", $aid);
	            setcookie("password", $this->encrypt_password($password));
	            setcookie("user", $_SESSION['user']['user']);
	        }
	        return true;
	    }
	    else
	    {
	        return false;
	    }
	}
	
	
	
	/**
	 * Encrypts a password.
	 *
	 * @param $password password to encrypt.
	 * @return string.
	 */
	function encrypt_password($password, $salt=SB_SALT)
	{
		return sha1(sha1($salt . $password));
	}
	
	function is_logged_in()
	{
		if($this->aid != -1)
			return true;
		else 
			return false;
	}
	
	
	function is_admin($aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;
		
		if($this->HasAccess(ALL_WEB, $aid))
			return true;
		else 	
			return false;
	}
	
	
	function GetAid()
	{
		return $this->aid;
	}
	
	
	function GetAllAdmins()
	{
		$res = $GLOBALS['db']->GetAll("SELECT aid FROM " . DB_PREFIX . "_admins");
		foreach($res AS $admin)
			$this->GetUserArray($admin['aid']);
		return $this->admins;
	}
	
	
	function GetAdmin($aid=-2)
	{
		if($aid == -2)
			$aid = $this->aid;
		if($aid < 0)
			return false;	
			
		$aid = (int)$aid;
		
		if(!isset($this->admins[$aid]))
			$this->GetUserArray($aid);
		return $this->admins[$aid];
	}
	
	
	function AddAdmin($name, $steam, $password, $email, $web_group, $web_flags, $srv_group, $srv_flags, $immunity, $srv_password)
	{		
		$add_admin = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_admins(user, authid, password, gid, email, extraflags, immunity, srv_group, srv_flags, srv_password)
											 VALUES (?,?,?,?,?,?,?,?,?,?)");
		$GLOBALS['db']->Execute($add_admin,array($name, $steam, $this->encrypt_password($password), $web_group, $email, $web_flags, $immunity, $srv_group, $srv_flags, $srv_password));
		return ($add_admin) ? (int)$GLOBALS['db']->Insert_ID() : -1;
	}
}
?>
