<?php
/**
 * =============================================================================
 * Build tabbed items
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: CTabsMenu.php 24 2007-11-06 18:17:05Z olly $
 * =============================================================================
 */

class CTabsMenu {
	var $menuItems = array( );
	
	function addMenuItem($title, $id, $description="", $url="", $external=false)
	{
		$curItem = array();
		$curItem['title'] = $title;
		$curItem['desc'] = $description;
		$curItem['url'] = $url;
		$curItem['external'] = $external;
		$curItem['id'] = $id;
		array_push($this->menuItems, $curItem);
	}
	
	function outputMenu()
	{
		$var = $this->menuItems;
		include TEMPLATES_PATH . "/admin.detail.navbar.php";
	}
	
	function getMenuArray()
	{
		return $this->menuItems;
	}
}

?>