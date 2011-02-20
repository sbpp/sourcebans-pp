<?php
/**
 * Build tabbed items
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
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